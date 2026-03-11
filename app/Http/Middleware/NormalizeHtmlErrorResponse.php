<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class NormalizeHtmlErrorResponse
{
    /**
     * Keep only the first complete HTML document when upstream/runtime
     * accidentally appends a second one to the same response body.
     */
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        if (! $this->shouldNormalize($response)) {
            return $response;
        }

        $content = $response->getContent();
        if (! is_string($content) || $content === '') {
            return $response;
        }

        $hasMultipleDocuments = preg_match_all('/<!doctype\s+html/i', $content) > 1
            || preg_match_all('/<html\b/i', $content) > 1;

        if (! $hasMultipleDocuments) {
            $normalizedErrorShell = $this->normalizeDuplicatedErrorShell($content);
            if ($normalizedErrorShell !== null) {
                $response->setContent($normalizedErrorShell);
            }

            return $response;
        }

        $firstHtmlClose = stripos($content, '</html>');
        if ($firstHtmlClose === false) {
            return $response;
        }

        $normalized = substr($content, 0, $firstHtmlClose + 7);
        if ($normalized !== false && $normalized !== '') {
            $response->setContent($normalized);
        }

        return $response;
    }

    private function shouldNormalize(Response $response): bool
    {
        if ($response->getStatusCode() < 400) {
            return false;
        }

        if (method_exists($response, 'isRedirection') && $response->isRedirection()) {
            return false;
        }

        $contentType = (string) $response->headers->get('content-type', '');

        return str_contains(strtolower($contentType), 'text/html');
    }

    private function normalizeDuplicatedErrorShell(string $content): ?string
    {
        if (! str_contains($content, 'error-page-shell')) {
            return null;
        }

        $bodyPos = stripos($content, '<body');
        if ($bodyPos === false) {
            return null;
        }

        $cutPos = false;
        $metaInBodyPos = stripos($content, '<meta', $bodyPos);
        if ($metaInBodyPos !== false) {
            $cutPos = $metaInBodyPos;
        }

        if ($cutPos === false) {
            $firstShellPos = stripos($content, '<div class="app-shell"');
            if ($firstShellPos !== false) {
                $secondShellPos = stripos($content, '<div class="app-shell"', $firstShellPos + 1);
                if ($secondShellPos !== false) {
                    $cutPos = $secondShellPos;
                }
            }
        }

        if ($cutPos === false) {
            return null;
        }

        $normalized = substr($content, 0, (int) $cutPos);
        if ($normalized === false || $normalized === '') {
            return null;
        }

        $normalized = rtrim($normalized);
        if (! preg_match('/<\/body>\s*$/i', $normalized)) {
            $normalized .= "\n</body>";
        }
        if (! preg_match('/<\/html>\s*$/i', $normalized)) {
            $normalized .= "\n</html>";
        }

        return $normalized;
    }
}
