<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Sentry\Laravel\Tracing\Middleware as BaseTracingMiddleware;

/**
 * ponytail: coerción defensiva si NormalizeIncomingTraceHeaders no corrió antes (orden de prepend).
 */
class SentryTracingMiddleware extends BaseTracingMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        foreach (['baggage', 'sentry-trace', 'traceparent'] as $name) {
            $value = $request->headers->get($name);
            if ($value !== null && ! is_string($value)) {
                $request->headers->set($name, is_scalar($value) ? (string) $value : '', true);
            }
        }

        return parent::handle($request, $next);
    }
}
