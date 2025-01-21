<?php

namespace App\Services\PaymentGateways;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

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

    public function createPaymentMethod(array $paymentMethodData): array
    {
        $response = Http::withHeaders($this->getHeaders())
            ->post($this->baseUrl . '/PaymentMethods', $paymentMethodData);
        
        return $this->handleResponse($response, 'payment method creation');
    }

    public function createCharge(array $chargeData): array
    {
        try {
            Log::info('Iniciando creación de cargo', ['data' => array_diff_key($chargeData, ['card' => true])]);

            $response = Http::withHeaders($this->getHeaders())
                ->post($this->baseUrl . '/Charges', $chargeData);
            
            $result = $this->handleResponse($response, 'charge creation');

            // Si requiere autenticación 3DS
            if (isset($result['authentication']) && isset($result['authentication']['url'])) {
                Log::info('Cargo requiere autenticación 3DS', [
                    'auth_url' => $result['authentication']['url'],
                    'auth_id' => $result['authentication']['id']
                ]);
            }

            return $result;
        } catch (\Exception $e) {
            Log::error('Error al crear cargo', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    public function processCharge(array $orderData, string $cardId, ?string $authenticationId = null): array
    {
        $chargeData = [
            'customer' => [
                'name' => $orderData['customer_name'],
                'email' => $orderData['customer_email'],
                'phoneNumber' => $orderData['customer_phone']
            ],
            'order' => [
                'amount' => $orderData['amount'],
                'description' => $orderData['description'] ?? 'Cargo por compra',
                'name' => $orderData['order_name'] ?? 'Orden de compra'
            ],
            'cardId' => $cardId
        ];

        if ($authenticationId) {
            $chargeData['authenticationId'] = $authenticationId;
        }

        if (!empty($orderData['metadata'])) {
            $chargeData['metadata'] = $orderData['metadata'];
        }

        return $this->createCharge($chargeData);
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
            ->post($this->baseUrl . '/Refunds', $refundData);
        
        return $this->handleResponse($response, 'refund creation');
    }

    public function updatePaymentMethod(array $updateData): array
    {
        $response = Http::withHeaders($this->getHeaders())
            ->put($this->baseUrl . '/PaymentMethods', $updateData);
        
        return $this->handleResponse($response, 'payment method update');
    }

}