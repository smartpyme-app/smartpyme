<?php

namespace App\Services\PaymentGateways;

use App\Contracts\PaymentGatewayInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

abstract class BasePaymentGateway implements PaymentGatewayInterface
{
    protected $apiKey;
    protected $baseUrl;
    protected $isSandbox;

    public function __construct()
    {
        $this->isSandbox = config('services.payment.sandbox_mode', true);
        $this->initialize();
    }

    abstract protected function initialize(): void;
    
    protected function getHeaders(): array
    {
        return [
            'Authorization' => $this->getAuthorizationHeader(),
            'Content-Type' => 'application/json'
        ];
    }

    abstract protected function getAuthorizationHeader(): string;

    protected function handleResponse($response, string $operation)
    {
        if ($response->successful()) {
            Log::info("$operation successful", [
                'gateway' => static::class,
                'response' => $response->json()
            ]);
            return $response->json();
        }

        Log::error("Error in $operation", [
            'gateway' => static::class,
            'error' => $response->json()
        ]);
        
        throw new \Exception("Error in $operation: " . $response->body());
    }
}