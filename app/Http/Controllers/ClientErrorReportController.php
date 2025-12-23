<?php

namespace App\Http\Controllers;

use App\Models\ClientErrorReport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ClientErrorReportController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'message' => ['required', 'string', 'max:2000'],
            'stack' => ['nullable', 'string', 'max:12000'],
            'url' => ['required', 'string', 'max:2048'],
            'line' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'column' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'severity' => ['nullable', 'in:error,warning,info'],
            'source' => ['nullable', 'in:error,unhandledrejection'],
            'metadata' => ['nullable', 'array'],
        ]);

        $metadata = $data['metadata'] ?? [];
        if (!empty($data['source'])) {
            $metadata['source'] = $data['source'];
        }
        if (empty($metadata)) {
            $metadata = null;
        }

        $requestId = $request->headers->get('X-Request-Id');
        $requestId = is_string($requestId) && $requestId !== '' ? $requestId : null;
        $userAgent = $request->userAgent();
        $userAgent = $userAgent ? Str::limit($userAgent, 512, '') : null;

        ClientErrorReport::create([
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

        return response()->json(['status' => 'ok'], 201);
    }
}
