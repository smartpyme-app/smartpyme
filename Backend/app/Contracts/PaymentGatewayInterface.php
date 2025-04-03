<?php

namespace App\Contracts;

interface PaymentGatewayInterface
{
    public function createCustomer(array $customerData): array;
    public function processPayment(array $paymentData): array;
    public function createSubscription(array $subscriptionData): array;
    public function cancelSubscription(string $subscriptionId): bool;
    public function getCustomer(string $customerId): array;
    public function createRefund(array $refundData): array;
}