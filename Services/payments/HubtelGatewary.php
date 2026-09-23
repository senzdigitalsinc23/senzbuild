<?php
namespace Services\Payments;

use GuzzleHttp\Client;

class HubtelGateway implements PaymentGatewayInterface
{
    protected string $baseUrl;
    protected string $clientId;
    protected string $clientSecret;
    protected string $callbackUrl;
    protected Client $http;

    public function __construct()
    {
        $this->baseUrl      = getenv('HUBTEL_BASE_URL');
        $this->clientId     = getenv('HUBTEL_CLIENT_ID');
        $this->clientSecret = getenv('HUBTEL_CLIENT_SECRET');
        $this->callbackUrl  = getenv('HUBTEL_CALLBACK_URL');
        $this->http         = new Client();
    }

    public function charge(string $phone, float $amount, string $reference): array
    {
        $response = $this->http->post("{$this->baseUrl}/items/initiate", [
            'auth' => [$this->clientId, $this->clientSecret],
            'json' => [
                'amount' => number_format($amount, 2, '.', ''),
                'customerMsisdn' => $phone,
                'description' => "Payment {$reference}",
                'clientReference' => $reference,
                'callbackUrl' => $this->callbackUrl,
            ]
        ]);

        return [
            'provider' => 'Hubtel',
            'referenceId' => $reference,
            'status' => $response->getStatusCode(),
            'body' => json_decode($response->getBody(), true)
        ];
    }
}
