<?php

namespace App\Support;

use Symfony\Component\HttpFoundation\Response;

class ErrorHtmlNormalizer
{
    public function normalizeResponse(Response $response): Response
    {
        if (! $this->shouldNormalize($response)) {
            return $response;
        }

        $content = $response->getContent();
        if (! is_string($content) || $content === '') {
            return $response;
        }

        $normalized = $this->normalizeContent($content);
        if ($normalized !== null && $normalized !== $content) {
            $response->setContent($normalized);
        }

        return $response;
    }

    public function normalizeContent(string $content): ?string
    {
        $normalized = $content;

        $trimmedLeadingGarbage = $this->trimLeadingGarbageBeforeDocument($normalized);
        if ($trimmedLeadingGarbage !== null) {
            $normalized = $trimmedLeadingGarbage;
        }

        $singleDocument = $this->keepFirstCompleteDocument($normalized);
        if ($singleDocument !== null) {
            $normalized = $singleDocument;
        }

        $normalizedErrorShell = $this->normalizeDuplicatedErrorShell($normalized);
        if ($normalizedErrorShell !== null) {
            $normalized = $normalizedErrorShell;
        }

        return $normalized !== $content ? $normalized : null;
    }

    private function shouldNormalize(Response $response): bool
    {
        if ($response->getStatusCode() < 400) {
            return false;
        }

        if ($response->isRedirection()) {
            return false;
        }

        $contentType = strtolower((string) $response->headers->get('content-type', ''));

        if ($contentType !== '') {
            return str_contains($contentType, 'text/html');
        }

        $content = $response->getContent();

        return is_string($content) && str_contains(strtolower($content), '<html');
    }

    private function trimLeadingGarbageBeforeDocument(string $content): ?string
    {
        $doctypePos = stripos($content, '<!doctype');
        $htmlPos = stripos($content, '<html');

        $positions = array_filter(
            [$doctypePos, $htmlPos],
            static fn ($position): bool => $position !== false
        );

        if ($positions === []) {
            return null;
        }

        $firstPos = min($positions);
        if ($firstPos <= 0) {
            return null;
        }

        $trimmed = substr($content, (int) $firstPos);

        return $trimmed !== '' ? $trimmed : null;
    }

    private function keepFirstCompleteDocument(string $content): ?string
    {
        $doctypeCount = preg_match_all('/<!doctype\s+html/i', $content, $doctypeMatches, PREG_OFFSET_CAPTURE);
        $htmlCount = preg_match_all('/<html\b/i', $content, $htmlMatches, PREG_OFFSET_CAPTURE);

        $hasMultipleDocuments = $doctypeCount >= 2
            || ($doctypeCount === 0 && $htmlCount >= 2);

        if (! $hasMultipleDocuments) {
            return null;
        }

        $startPositions = [];

        if ($doctypeCount >= 2) {
            foreach ($doctypeMatches[0] as $match) {
                $startPositions[] = (int) $match[1];
            }
        } else {
            foreach ($htmlMatches[0] as $match) {
                $startPositions[] = (int) $match[1];
            }
        }

        $startPositions = array_values(array_unique($startPositions));
        sort($startPositions);

        foreach ($startPositions as $index => $startPos) {
            $nextStartPos = $startPositions[$index + 1] ?? null;
            $closePos = stripos($content, '</html>', $startPos);

            if ($closePos === false) {
                continue;
            }

            if ($nextStartPos !== null && $closePos >= $nextStartPos) {
                continue;
            }

            $candidate = substr($content, $startPos, $closePos + 7 - $startPos);
            if ($candidate !== '') {
                return $candidate;
            }
        }

        $lastStartPos = end($startPositions);
        $lastClosePos = strripos($content, '</html>');

        if ($lastStartPos === false || $lastClosePos === false || $lastClosePos <= $lastStartPos) {
            return null;
        }

        $fallback = substr($content, (int) $lastStartPos, $lastClosePos + 7 - (int) $lastStartPos);

        return $fallback !== '' ? $fallback : null;
    }

    private function normalizeDuplicatedErrorShell(string $content): ?string
    {
        if (! str_contains($content, 'error-page-shell')) {
            return null;
        }

        if (! preg_match('/<body\b[^>]*>/i', $content, $bodyOpenMatch, PREG_OFFSET_CAPTURE)) {
            return null;
        }

        $bodyOpenTag = $bodyOpenMatch[0][0];
        $bodyOpenPos = (int) $bodyOpenMatch[0][1];
        $bodyContentStart = $bodyOpenPos + strlen($bodyOpenTag);
        $bodyClosePos = stripos($content, '</body>', $bodyContentStart);

        if ($bodyClosePos === false) {
            return null;
        }

        $bodyInner = substr($content, $bodyContentStart, $bodyClosePos - $bodyContentStart);
        $normalizedBodyInner = $this->removeLeadingEmptyErrorShell($bodyInner);
        [$headLeak, $normalizedBodyInner] = $this->extractLeadingHeadTags($normalizedBodyInner);

        if ($normalizedBodyInner === $bodyInner && $headLeak === null) {
            return null;
        }

        $normalized = substr($content, 0, $bodyContentStart)
            .$normalizedBodyInner
            .substr($content, $bodyClosePos);

        if ($headLeak !== null) {
            $headClosePos = stripos($normalized, '</head>');
            if ($headClosePos !== false) {
                $normalized = substr($normalized, 0, $headClosePos)
                    ."\n".trim($headLeak)."\n"
                    .substr($normalized, $headClosePos);
            }
        }

        return $normalized;
    }

    private function removeLeadingEmptyErrorShell(string $bodyInner): string
    {
        if (! preg_match('/^\s*<div class="app-shell">\s*<\/div>\s*/i', $bodyInner, $match)) {
            return $bodyInner;
        }

        $tail = substr($bodyInner, strlen($match[0]));
        if ($tail === '') {
            return $bodyInner;
        }

        if (! preg_match('/<div class="app-shell"/i', $tail)) {
            return $bodyInner;
        }

        return ltrim($tail);
    }

    /**
     * @return array{0: string|null, 1: string}
     */
    private function extractLeadingHeadTags(string $bodyInner): array
    {
        $pattern = '/^\s*((?:(?:<meta\b[^>]*>\s*)|(?:<link\b[^>]*>\s*)|(?:<title\b[^>]*>[\s\S]*?<\/title>\s*)|(?:<style\b[^>]*>[\s\S]*?<\/style>\s*)|(?:<script\b[^>]*>[\s\S]*?<\/script>\s*))+)([\s\S]*)$/i';

        if (! preg_match($pattern, $bodyInner, $matches)) {
            return [null, $bodyInner];
        }

        $remainingBody = ltrim($matches[2]);
        if (! preg_match('/^<div class="app-shell"/i', $remainingBody)) {
            return [null, $bodyInner];
        }

        return [trim($matches[1]), $remainingBody];
    }
}
