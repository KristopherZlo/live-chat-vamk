<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    /**
     * Add common security headers to the response.
     */
    public function handle(Request $request, Closure $next): Response
    {
        /** @var \Symfony\Component\HttpFoundation\Response $response */
        $response = $next($request);

        $headers = [
            'X-Frame-Options' => 'SAMEORIGIN',
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Permissions-Policy' => "geolocation=(), microphone=(), camera=(), fullscreen=(self \"https://www.youtube.com\" \"https://www.youtube-nocookie.com\"), payment=()",
            'Cross-Origin-Opener-Policy' => 'same-origin',
            'Cross-Origin-Resource-Policy' => 'same-origin',
        ];

        $headers['Content-Security-Policy'] = $this->buildContentSecurityPolicy($request);

        // HSTS only for HTTPS requests; browsers will cache this for 180 days.
        if ($this->isSecureRequest($request)) {
            $headers['Strict-Transport-Security'] = 'max-age=15552000; includeSubDomains; preload';
        }

        foreach ($headers as $name => $value) {
            if (!$response->headers->has($name)) {
                $response->headers->set($name, $value);
            }
        }

        return $response;
    }

    /**
     * Determine if the request is HTTPS, including proxied HTTPS signals.
     */
    protected function isSecureRequest(Request $request): bool
    {
        if ($request->isSecure()) {
            return true;
        }

        $forwardedProto = (string) $request->headers->get('X-Forwarded-Proto', '');
        if ($forwardedProto === '') {
            return false;
        }

        $parts = array_map('trim', explode(',', strtolower($forwardedProto)));

        return in_array('https', $parts, true);
    }

    /**
     * Build the Content Security Policy header. Defaults are strict for production,
     * but allow inline scripts and Vite dev server origins when running locally.
     */
    protected function buildContentSecurityPolicy(Request $request): string
    {
        $defaultSrc = ["'self'"];
        $imgSrc = ["'self'", 'data:'];
        $styleSrc = ["'self'", "'unsafe-inline'", 'https://fonts.googleapis.com', 'https://cdn.jsdelivr.net'];
        $scriptSrc = ["'self'", "'unsafe-inline'", "'unsafe-eval'", 'https://cdn.jsdelivr.net'];
        $fontSrc = ["'self'", 'data:', 'https://fonts.gstatic.com'];
        $connectSrc = ["'self'", 'https://cdn.jsdelivr.net'];
        $frameSrc = ["'self'", 'https://www.youtube.com', 'https://www.youtube-nocookie.com'];

        if (app()->environment('local')) {
            $devOrigins = $this->getDevOrigins($request);

            foreach ($devOrigins as $origin) {
                $styleSrc[] = $origin;
                $scriptSrc[] = $origin;
                $connectSrc[] = $origin;
                $imgSrc[] = $origin;
            }

            foreach ($this->getDevWebsocketOrigins($devOrigins) as $wsOrigin) {
                $connectSrc[] = $wsOrigin;
            }

        }

        // Allow realtime connections (e.g., Reverb/Pusher).
        foreach ($this->getRealtimeOrigins($request) as $origin) {
            $connectSrc[] = $origin;
        }

        // Accept both 127.0.0.1 and localhost for Reverb/Pusher by default.
        $connectSrc[] = 'http://localhost:8080';
        $connectSrc[] = 'ws://localhost:8080';
        $connectSrc[] = 'http://127.0.0.1:8080';
        $connectSrc[] = 'ws://127.0.0.1:8080';

        $directives = [
            'default-src' => $defaultSrc,
            'img-src' => $imgSrc,
            'style-src' => $styleSrc,
            'script-src' => $scriptSrc,
            'font-src' => $fontSrc,
            'connect-src' => $connectSrc,
            'object-src' => ["'none'"],
            'base-uri' => ["'self'"],
            'frame-ancestors' => ["'self'"],
            'frame-src' => $frameSrc,
            'form-action' => ["'self'"],
        ];

        $parts = [];
        foreach ($directives as $name => $sources) {
            $uniqueSources = array_values(array_unique(array_filter($sources)));
            $parts[] = $name.' '.implode(' ', $uniqueSources);
        }

        return implode('; ', $parts);
    }

    /**
     * Build a list of HTTP origins that should be allowed for dev assets.
     */
    protected function getDevOrigins(Request $request): array
    {
        $protocol = env('VITE_DEV_PROTOCOL', 'http');
        $host = env('VITE_DEV_HOST', 'localhost');
        $hmrHost = env('VITE_DEV_HMR_HOST');
        if (!$hmrHost) {
            $hmrHost = $host;
        }
        $port = env('VITE_DEV_PORT', 5173);
        $origin = env('VITE_DEV_ORIGIN');
        $requestHost = $request->getHost();
        $localIp = gethostbyname(gethostname());

        $origins = [
            $origin,
            $host ? sprintf('%s://%s:%s', $protocol, $host, $port) : null,
            $hmrHost ? sprintf('%s://%s:%s', $protocol, $hmrHost, $port) : null,
            sprintf('%s://localhost:%s', $protocol, $port),
            sprintf('%s://127.0.0.1:%s', $protocol, $port),
            $requestHost ? sprintf('%s://%s:%s', $protocol, $requestHost, $port) : null,
            $localIp ? sprintf('%s://%s:%s', $protocol, $localIp, $port) : null,
        ];

        return array_values(array_unique(array_filter($origins)));
    }

    /**
     * Derive websocket origins from the HTTP origins for HMR connections.
     */
    protected function getDevWebsocketOrigins(array $httpOrigins): array
    {
        $wsOrigins = [];

        foreach ($httpOrigins as $origin) {
            $parts = parse_url($origin);
            if (! $parts || empty($parts['host'])) {
                continue;
            }

            $scheme = ($parts['scheme'] ?? 'http') === 'https' ? 'wss' : 'ws';
            $host = $parts['host'];
            $port = isset($parts['port']) ? ':'.$parts['port'] : '';

            $wsOrigins[] = sprintf('%s://%s%s', $scheme, $host, $port);
        }

        return array_values(array_unique($wsOrigins));
    }

    /**
     * Build websocket/HTTP origins for realtime (Reverb/Pusher) connections.
     */
    protected function getRealtimeOrigins(Request $request): array
    {
        $host = env('REVERB_HOST', env('VITE_REVERB_HOST', $request->getHost() ?: 'localhost'));
        $port = env('REVERB_PORT', env('VITE_REVERB_PORT', 8080));
        $scheme = env('REVERB_SCHEME', env('VITE_REVERB_SCHEME', 'http'));

        $httpOrigin = sprintf('%s://%s:%s', $scheme, $host, $port);
        $wsScheme = $scheme === 'https' ? 'wss' : 'ws';
        $wsOrigin = sprintf('%s://%s:%s', $wsScheme, $host, $port);

        return array_values(array_unique([$httpOrigin, $wsOrigin]));
    }
}
