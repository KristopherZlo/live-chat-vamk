<?php

namespace App\Http\Middleware;

use App\Support\ErrorHtmlNormalizer;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class NormalizeHtmlErrorResponse
{
    public function __construct(private readonly ErrorHtmlNormalizer $normalizer)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        return $this->normalizer->normalizeResponse($response);
    }
}
