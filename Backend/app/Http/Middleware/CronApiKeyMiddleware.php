<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Aws\SecretsManager\SecretsManagerClient;

class CronApiKeyMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $apiKey = $request->header('X-Cron-API-Key');
        
        // Get API key from AWS Secrets Manager
        try {
            $secretArn = env('CRON_API_KEY_SECRET_ARN', 'smartpyme/cron-api-key');
            $client = new SecretsManagerClient([
                'region' => env('AWS_DEFAULT_REGION', 'us-east-2'),
                'version' => 'latest'
            ]);
            $result = $client->getSecretValue(['SecretId' => $secretArn]);
            $secret = json_decode($result['SecretString'], true);
            $expectedKey = $secret['api_key'];
        } catch (\Exception $e) {
            Log::error('Failed to get cron API key from Secrets Manager: ' . $e->getMessage());
            return response()->json(['error' => 'Internal Server Error'], 500);
        }
        
        if ($apiKey !== $expectedKey) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
        
        return $next($request);
    }
}