<?php
namespace Services\Payments;

use Services\Payments\PaymentGatewayInterface;
use Stripe\Stripe;
use Stripe\PaymentIntent;

class StripeGateway implements PaymentGatewayInterface
{
    public function __construct(string $apiKey)
    {
        Stripe::setApiKey($apiKey);
    }

    public function charge(float $amount, string $currency, array $options = []): array
    {
        $paymentIntent = PaymentIntent::create([
            'amount' => intval($amount * 100), // cents
            'currency' => $currency,
            'payment_method' => $options['payment_method'] ?? null,
            'confirm' => true,
        ]);

        return $paymentIntent->toArray();
    }

    public function refund(string $transactionId, float $amount): array
    {
        $refund = \Stripe\Refund::create([
            'payment_intent' => $transactionId,
            'amount' => intval($amount * 100),
        ]);
        return $refund->toArray();
    }

    public function getTransaction(string $transactionId): array
    {
        $paymentIntent = PaymentIntent::retrieve($transactionId);
        return $paymentIntent->toArray();
    }
}
