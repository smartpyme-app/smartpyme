<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * ponytail: el SDK de Sentry (continueTrace → DynamicSamplingContext) asume headers W3C en string;
 * valores no escalares (p. ej. proxy/load balancer) rompen str_contains() en el vendor.
 */
class NormalizeIncomingTraceHeaders
{
    // ponytail: Authorization con valores anidados rompe bearerToken() → str_contains() en Laravel.
    private const TRACE_HEADERS = ['baggage', 'sentry-trace', 'traceparent', 'authorization'];

    public function handle(Request $request, Closure $next): Response
    {
        foreach (self::TRACE_HEADERS as $name) {
            $values = $request->headers->all($name);
            if ($values === []) {
                continue;
            }
            $flat = $this->flattenHeaderValues($values);
            if ($flat !== '') {
                $request->headers->set($name, $flat, true);
            } else {
                $request->headers->remove($name);
            }
        }

        return $next($request);
    }

    /**
     * @param  list<string|array<int, string>>  $values
     */
    private function flattenHeaderValues(array $values): string
    {
        $parts = [];
        foreach ($values as $value) {
            if (is_array($value)) {
                foreach ($value as $nested) {
                    if (is_string($nested) && $nested !== '') {
                        $parts[] = $nested;
                    }
                }
                continue;
            }
            if (is_string($value) && $value !== '') {
                $parts[] = $value;
            }
        }

        return implode(',', $parts);
    }
}
