<?php

namespace App\Helpers;

use Aws\Ssm\SsmClient;
use Aws\SecretsManager\SecretsManagerClient;

class AwsConfigHelper
{
    private static $ssmClient;
    private static $secretsClient;
    private static $cache = [];

    private static function getSsmClient()
    {
        if (!self::$ssmClient) {
            self::$ssmClient = new SsmClient([
                'region' => env('AWS_DEFAULT_REGION', 'us-east-2'),
                'version' => 'latest'
            ]);
        }
        return self::$ssmClient;
    }

    private static function getSecretsClient()
    {
        if (!self::$secretsClient) {
            self::$secretsClient = new SecretsManagerClient([
                'region' => env('AWS_DEFAULT_REGION', 'us-east-2'),
                'version' => 'latest'
            ]);
        }
        return self::$secretsClient;
    }

    public static function getParameter($parameterName, $default = null)
    {
        $cacheKey = 'param_' . md5($parameterName);
        
        // Return cached value if available
        if (isset(self::$cache[$cacheKey])) {
            return self::$cache[$cacheKey];
        }
        
        try {
            $result = self::getSsmClient()->getParameter([
                'Name' => $parameterName
            ]);
            $value = $result['Parameter']['Value'];
            
            // Cache the value for this request
            self::$cache[$cacheKey] = $value;
            
            return $value;
        } catch (\Exception $e) {
            error_log("Failed to get parameter {$parameterName}: " . $e->getMessage());
            return $default;
        }
    }

    public static function getSecret($secretId, $key = null, $default = null)
    {
        $cacheKey = 'secret_' . md5($secretId . '_' . ($key ?? ''));
        
        // Return cached value if available
        if (isset(self::$cache[$cacheKey])) {
            return self::$cache[$cacheKey];
        }
        
        try {
            $result = self::getSecretsClient()->getSecretValue([
                'SecretId' => $secretId
            ]);
            
            $secretString = $result['SecretString'];
            
            // Try to decode as JSON first
            $secretValue = json_decode($secretString, true);
            
            // If it's not JSON, treat as plain string
            if (json_last_error() !== JSON_ERROR_NONE) {
                $value = $key ? $default : $secretString;
            } else {
                $value = $key ? ($secretValue[$key] ?? $default) : $secretValue;
            }
            
            // Cache the value for this request
            self::$cache[$cacheKey] = $value;
            
            return $value;
        } catch (\Exception $e) {
            error_log("Failed to get secret {$secretId}: " . $e->getMessage());
            return $default;
        }
    }
}