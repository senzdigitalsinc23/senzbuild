<?php
namespace Services\Payments;

interface PaymentGatewayInterface
{
    public function charge(float $amount, string $currency, array $options = []): array;

    public function refund(string $transactionId, float $amount): array;

    public function getTransaction(string $transactionId): array;
}
