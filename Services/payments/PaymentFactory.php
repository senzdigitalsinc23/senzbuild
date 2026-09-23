<?php
namespace Services\Payments;

class PaymentFactory
{
    public static function make(string $gateway, array $config): PaymentGatewayInterface
    {
        return match ($gateway) {
            'stripe' => new StripeGateway($config['secret']),
            'paypal' => new PayPalGateway($config['client_id'], $config['secret'], $config['mode'] ?? 'sandbox'),
            default  => throw new \Exception("Unsupported gateway: $gateway")
        };
    }
}
