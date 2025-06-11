<?php

namespace App\Http\Middleware;

use Closure;
use Psr\Log\LogLevel;
use App\Jobs\AsyncLoggerJob;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LogApiRequestMiddleware
{
    /**
     * Logs basic request and response data asynchronously.
     * Excludes sensitive fields and measures request duration.
     *
     * @param Request $request
     * @param Closure(Request): Response $next
     * @return Response
     */
    public function handle(Request $request, Closure $next): Response
    {
        $start = hrtime(true);
        $response = $next($request);
        $durationMs = round((hrtime(true) - $start) / 1e+6, 3);

        $contentType = $response->headers->get('Content-Type', '');

        AsyncLoggerJob::dispatch(
            message: 'API request logged',
            level: LogLevel::INFO,
            context: [
                'request' => [
                    'method' => $request->method(),
                    'url' => $request->fullUrl(),
                    'body' => $request->except(['password', 'token']),
                    'ip' => $request->ip(),
                ],
                'response' => [
                    'status' => $response->getStatusCode(),
                    'headers' => $response->headers->all(),
                    'body' => str_contains($contentType, 'application/json')
                        ? json_decode($response->getContent(), true)
                        : null,
                ],
                'duration_ms' => $durationMs,
            ],
        );

        return $response;
    }
}
