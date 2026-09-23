<?php
namespace Services\Payments;

class PaymentService
{
    protected PaymentGatewayInterface $gateway;

    public function __construct(string $provider)
    {
        switch (strtolower($provider)) {
            case 'mtn':
                $this->gateway = new MTNMomoGateway();
                break;
            case 'vodafone':
            case 'airteltigo':
                $this->gateway = new HubtelGateway();
                break;
            case 'paystack':
                $this->gateway = new PaystackGateway();
                break;
            default:
                throw new \Exception("Unsupported provider: {$provider}");
        }
    }

    public function charge(string $phone, float $amount, string $reference): array
    {
        return $this->gateway->charge($phone, $amount, $reference);
    }
}
