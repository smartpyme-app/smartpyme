<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Helpers\AwsConfigHelper;

class CronApiKeyMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $apiKey = $request->header('X-Cron-API-Key');
        
        $secretId = env('CRON_API_KEY_SECRET_ARN', 'smartpyme/cron-api-key');
        $expectedKey = AwsConfigHelper::getSecret($secretId, 'api_key');
        
        if (!$expectedKey) {
            Log::error('Failed to get cron API key from Secrets Manager');
            return response()->json(['error' => 'Internal Server Error'], 500);
        }
        
        if ($apiKey !== $expectedKey) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
        
        return $next($request);
    }
}