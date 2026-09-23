<?php
namespace Services\Payments;

use PayPal\Rest\ApiContext;
use PayPal\Auth\OAuthTokenCredential;
use PayPal\Api\Payment;
use PayPal\Api\PaymentExecution;

class PayPalGateway implements PaymentGatewayInterface
{
    protected ApiContext $apiContext;

    public function __construct(string $clientId, string $secret, string $mode = 'sandbox')
    {
        $this->apiContext = new ApiContext(
            new OAuthTokenCredential($clientId, $secret)
        );
        $this->apiContext->setConfig(['mode' => $mode]);
    }

    public function charge(float $amount, string $currency, array $options = []): array
    {
        // In PayPal, charge requires redirect to approval URL
        // Here you’d generate a payment and return approval URL
        // (simplified for demo)
        return [
            'approval_url' => 'https://sandbox.paypal.com/checkout?token=abc123',
            'status' => 'pending'
        ];
    }

    public function refund(string $transactionId, float $amount): array
    {
        // Simplified refund stub
        return [
            'transaction_id' => $transactionId,
            'status' => 'refunded',
            'amount' => $amount,
        ];
    }

    public function getTransaction(string $transactionId): array
    {
        return ['transaction_id' => $transactionId, 'status' => 'completed'];
    }
}
