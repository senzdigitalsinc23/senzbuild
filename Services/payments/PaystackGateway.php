<?php
namespace Services\Payments;

use GuzzleHttp\Client;

class PaystackGateway implements PaymentGatewayInterface
{
    protected string $baseUrl;
    protected string $secretKey;
    protected string $callbackUrl;
    protected Client $http;

    public function __construct()
    {
        $this->baseUrl    = getenv('PAYSTACK_BASE_URL');
        $this->secretKey  = getenv('PAYSTACK_SECRET_KEY');
        $this->callbackUrl= getenv('PAYSTACK_CALLBACK_URL');
        $this->http       = new Client();
    }

    public function charge(string $phone, float $amount, string $reference): array
    {
        $response = $this->http->post("{$this->baseUrl}/charge", [
            'headers' => [
                'Authorization' => "Bearer {$this->secretKey}",
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'amount' => intval($amount * 100), // Paystack expects kobo
                'currency' => 'GHS',
                'mobile_money' => [
                    'phone' => $phone,
                    'provider' => 'mtn' // or 'vodafone', 'airteltigo'
                ],
                'reference' => $reference,
                'callback_url' => $this->callbackUrl
            ]
        ]);

        return [
            'provider' => 'Paystack',
            'referenceId' => $reference,
            'status' => $response->getStatusCode(),
            'body' => json_decode($response->getBody(), true)
        ];
    }
}
