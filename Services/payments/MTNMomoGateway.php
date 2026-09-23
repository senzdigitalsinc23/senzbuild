<?php
namespace Services\Payments;

use GuzzleHttp\Client;

class MTNMomoGateway implements PaymentGatewayInterface
{
    protected string $baseUrl;
    protected string $primaryKey;
    protected string $userId;
    protected string $callbackUrl;
    protected Client $http;

    public function __construct()
    {
        $this->baseUrl     = getenv('MOMO_BASE_URL');
        $this->primaryKey  = getenv('MOMO_PRIMARY_KEY');
        $this->userId      = getenv('MOMO_USER_ID');
        $this->callbackUrl = getenv('MOMO_CALLBACK_URL');
        $this->http        = new Client();
    }

    public function charge(string $phone, float $amount, string $reference): array
    {
        $uuid = $this->generateUuid();

        $response = $this->http->post("{$this->baseUrl}/requesttopay", [
            'headers' => [
                'Authorization' => "Bearer {$this->primaryKey}",
                'X-Reference-Id' => $uuid,
                'X-Target-Environment' => 'sandbox',
                'Ocp-Apim-Subscription-Key' => $this->primaryKey,
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'amount' => number_format($amount, 2, '.', ''),
                'currency' => getenv('MOMO_CURRENCY'),
                'externalId' => $reference,
                'payer' => [
                    'partyIdType' => 'MSISDN',
                    'partyId' => $phone
                ],
                'payerMessage' => "Payment for {$reference}",
                'payeeNote' => "Reference: {$reference}"
            ],
        ]);

        return [
            'provider' => 'MTN MoMo',
            'referenceId' => $uuid,
            'status' => $response->getStatusCode()
        ];
    }

    private function generateUuid(): string
    {
        return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }
}
