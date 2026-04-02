<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class TraceRequest
{
    public function handle(Request $request, Closure $next): Response
    {
        $traceId = $request->header('X-Trace-Id', bin2hex(random_bytes(16)));
        $startTime = microtime(true);
        $request->headers->set('X-Trace-Id', $traceId);

        $response = $next($request);
        $duration = round((microtime(true) - $startTime) * 1000, 2);

        if (method_exists($response, 'header')) {
            $response->header('X-Trace-Id', $traceId);
            $response->header('X-Response-Time', "{$duration}ms");
        }

        Log::info('[Gateway]', [
            'trace_id' => $traceId, 'method' => $request->method(),
            'path' => $request->path(), 'status' => $response->getStatusCode(),
            'duration_ms' => $duration, 'ip' => $request->ip(),
        ]);

        return $response;
    }
}
