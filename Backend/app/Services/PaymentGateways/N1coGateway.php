<?php

namespace App\Services\PaymentGateways;

use Illuminate\Support\Facades\Http;

class N1coGateway extends BasePaymentGateway
{
    protected function initialize(): void
    {
        $this->apiKey = $this->isSandbox 
            ? config('services.nico.sandbox_api_key')
            : config('services.nico.api_key');
        
        $this->baseUrl = $this->isSandbox 
            ? config('services.nico.sandbox_url')
            : config('services.nico.base_url');
    }

    protected function getAuthorizationHeader(): string
    {
        return 'Bearer ' . $this->apiKey;
    }

    public function createCustomer(array $customerData): array
    {
        $response = Http::withHeaders($this->getHeaders())
            ->post($this->baseUrl . '/customers', $customerData);
        
        return $this->handleResponse($response, 'customer creation');
    }

    public function processPayment(array $paymentData): array
    {
        $response = Http::withHeaders($this->getHeaders())
            ->post($this->baseUrl . '/payments', $paymentData);
        
        return $this->handleResponse($response, 'payment processing');
    }

    public function createSubscription(array $subscriptionData): array
    {
        $response = Http::withHeaders($this->getHeaders())
            ->post($this->baseUrl . '/subscriptions', $subscriptionData);
        
        return $this->handleResponse($response, 'subscription creation');
    }

    public function cancelSubscription(string $subscriptionId): bool
    {
        $response = Http::withHeaders($this->getHeaders())
            ->delete($this->baseUrl . "/subscriptions/{$subscriptionId}");
        
        return $response->successful();
    }

    public function getCustomer(string $customerId): array
    {
        $response = Http::withHeaders($this->getHeaders())
            ->get($this->baseUrl . "/customers/{$customerId}");
        
        return $this->handleResponse($response, 'get customer');
    }

    public function createRefund(array $refundData): array
    {
        $response = Http::withHeaders($this->getHeaders())
            ->post($this->baseUrl . '/refunds', $refundData);
        
        return $this->handleResponse($response, 'refund creation');
    }
}