<?php

namespace App\Services;

use App\Contracts\PaymentGatewayInterface;
use App\Services\PaymentGateways\N1coGateway;
use App\Services\PaymentGateways\StripeGateway;
use Exception;
use Illuminate\Support\Facades\Log;

class PaymentService
{
    private $gateway;

    public function __construct(string $gatewayName = null)
    {
        $gatewayName = $gatewayName ?? config('services.payment.default_gateway', 'n1co');
        $this->gateway = $this->resolveGateway($gatewayName);
    }

    /**
     * Resuelve qué gateway usar basado en el nombre
     */
    private function resolveGateway(string $gatewayName): PaymentGatewayInterface
    {
        switch ($gatewayName) {
            case 'n1co':
                return new N1coGateway();
            default:
                throw new Exception("Pasarela de pago no soportada: $gatewayName");
        }
    }

    /**
     * Procesa un pago
     */
    public function processPayment(array $paymentData): array
    {
        try {
            Log::info('Iniciando procesamiento de pago', ['data' => $paymentData]);
            
            $result = $this->gateway->processPayment($paymentData);
            
            Log::info('Pago procesado exitosamente', ['result' => $result]);
            
            return $result;
        } catch (Exception $e) {
            Log::error('Error procesando pago', [
                'error' => $e->getMessage(),
                'data' => $paymentData
            ]);
            throw $e;
        }
    }

    /**
     * Crea un nuevo cliente
     */
    public function createCustomer(array $customerData): array
    {
        try {
            Log::info('Creando nuevo cliente', ['data' => $customerData]);
            
            $customer = $this->gateway->createCustomer($customerData);
            
            Log::info('Cliente creado exitosamente', ['customer' => $customer]);
            
            return $customer;
        } catch (Exception $e) {
            Log::error('Error creando cliente', [
                'error' => $e->getMessage(),
                'data' => $customerData
            ]);
            throw $e;
        }
    }

    /**
     * Crea una nueva suscripción
     */
    public function createSubscription(array $subscriptionData): array
    {
        try {
            Log::info('Creando nueva suscripción', ['data' => $subscriptionData]);
            
            $subscription = $this->gateway->createSubscription($subscriptionData);
            
            Log::info('Suscripción creada exitosamente', ['subscription' => $subscription]);
            
            return $subscription;
        } catch (Exception $e) {
            Log::error('Error creando suscripción', [
                'error' => $e->getMessage(),
                'data' => $subscriptionData
            ]);
            throw $e;
        }
    }

    /**
     * Cancela una suscripción existente
     */
    public function cancelSubscription(string $subscriptionId): bool
    {
        try {
            Log::info('Cancelando suscripción', ['subscription_id' => $subscriptionId]);
            
            $result = $this->gateway->cancelSubscription($subscriptionId);
            
            Log::info('Suscripción cancelada exitosamente', ['result' => $result]);
            
            return $result;
        } catch (Exception $e) {
            Log::error('Error cancelando suscripción', [
                'error' => $e->getMessage(),
                'subscription_id' => $subscriptionId
            ]);
            throw $e;
        }
    }

    /**
     * Obtiene información de un cliente
     */
    public function getCustomer(string $customerId): array
    {
        try {
            return $this->gateway->getCustomer($customerId);
        } catch (Exception $e) {
            Log::error('Error obteniendo cliente', [
                'error' => $e->getMessage(),
                'customer_id' => $customerId
            ]);
            throw $e;
        }
    }

    /**
     * Crea un reembolso
     */
    public function createRefund(array $refundData): array
    {
        try {
            Log::info('Iniciando reembolso', ['data' => $refundData]);
            
            $refund = $this->gateway->createRefund($refundData);
            
            Log::info('Reembolso procesado exitosamente', ['refund' => $refund]);
            
            return $refund;
        } catch (Exception $e) {
            Log::error('Error procesando reembolso', [
                'error' => $e->getMessage(),
                'data' => $refundData
            ]);
            throw $e;
        }
    }

    /**
     * Cambia la pasarela de pago en tiempo de ejecución
     */
    public function setGateway(string $gatewayName): void
    {
        $this->gateway = $this->resolveGateway($gatewayName);
    }
}