<?php

namespace App\Http\Controllers;

use App\Models\ClientErrorReport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ClientErrorReportController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'message' => [
                'required',
                'string',
                'max:' . config('ghostroom.limits.client_error.message_max', 2000),
            ],
            'stack' => [
                'nullable',
                'string',
                'max:' . config('ghostroom.limits.client_error.stack_max', 12000),
            ],
            'url' => [
                'required',
                'string',
                'max:' . config('ghostroom.limits.client_error.url_max', 2048),
            ],
            'line' => [
                'nullable',
                'integer',
                'min:0',
                'max:' . config('ghostroom.limits.client_error.line_max', 1000000),
            ],
            'column' => [
                'nullable',
                'integer',
                'min:0',
                'max:' . config('ghostroom.limits.client_error.column_max', 1000000),
            ],
            'severity' => ['nullable', 'in:error,warning,info'],
            'source' => ['nullable', 'in:error,unhandledrejection'],
            'metadata' => ['nullable', 'array'],
        ]);

        $metadata = $data['metadata'] ?? [];
        if (! empty($data['source'])) {
            $metadata['source'] = $data['source'];
        }
        if (empty($metadata)) {
            $metadata = null;
        }

        $requestId = $request->headers->get('X-Request-Id');
        $requestId = is_string($requestId) && $requestId !== '' ? $requestId : null;
        $userAgent = $request->userAgent();
        $userAgent = $userAgent ? Str::limit($userAgent, 512, '') : null;

        $report = ClientErrorReport::create([
            'severity' => $data['severity'] ?? 'error',
            'message' => $data['message'],
            'stack' => $data['stack'] ?? null,
            'url' => $data['url'],
            'line' => $data['line'] ?? null,
            'column' => $data['column'] ?? null,
            'user_id' => $request->user()?->id,
            'request_id' => $requestId,
            'ip_address' => $request->ip(),
            'user_agent' => $userAgent,
            'metadata' => $metadata,
        ]);

        Log::channel('client_errors')->warning('client.error.reported', [
            'id' => $report->id,
            'severity' => $report->severity,
            'message' => Str::limit($report->message, 500, '...'),
            'url' => $this->sanitizeUrl($report->url),
            'line' => $report->line,
            'column' => $report->column,
            'user_id' => $report->user_id,
            'request_id' => $report->request_id,
            'ip_address' => $report->ip_address,
            'user_agent' => $report->user_agent,
            'metadata' => $this->sanitizeMetadata($metadata),
        ]);

        return response()->json(['status' => 'ok'], 201);
    }

    private function sanitizeUrl(string $url): string
    {
        $parts = parse_url($url);
        if ($parts === false) {
            return Str::limit($url, 512, '');
        }

        $sanitized = '';
        if (! empty($parts['scheme'])) {
            $sanitized .= $parts['scheme'].'://';
        }
        if (! empty($parts['host'])) {
            $sanitized .= $parts['host'];
        }
        if (! empty($parts['port'])) {
            $sanitized .= ':'.$parts['port'];
        }
        if (! empty($parts['path'])) {
            $sanitized .= $parts['path'];
        }

        return Str::limit($sanitized, 512, '');
    }

    private function sanitizeMetadata(?array $metadata): array
    {
        if ($metadata === null) {
            return [];
        }

        $sanitized = [];

        foreach ($metadata as $key => $value) {
            if (count($sanitized) >= 25) {
                break;
            }

            $stringValue = $this->stringifyMetadataValue($value);
            $sanitized[(string) $key] = Str::limit($stringValue, 500, '...');
        }

        return $sanitized;
    }

    private function stringifyMetadataValue(mixed $value): string
    {
        if (is_scalar($value) || $value === null) {
            return (string) $value;
        }

        $encoded = json_encode($value);

        return $encoded !== false ? $encoded : '[unserializable]';
    }
}
