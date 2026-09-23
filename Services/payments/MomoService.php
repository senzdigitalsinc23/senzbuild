<?php

namespace App\Services\Payments;

use Exception;

class MomoService
{
    protected string $apiUser;
    protected string $apiKey;
    protected string $subscriptionKey;
    protected string $baseUrl;

    public function __construct()
    {
        $this->apiUser = $_ENV['MOMO_API_USER'];
        $this->apiKey = $_ENV['MOMO_API_KEY'];
        $this->subscriptionKey = $_ENV['MOMO_SUB_KEY'];
        $this->baseUrl = $_ENV['MOMO_BASE_URL'] ?? 'https://sandbox.momodeveloper.mtn.com';
    }

    /**
     * Generate access token from MoMo API
     */
    public function getAccessToken(): string
    {
        $url = $this->baseUrl . '/collection/token/';

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Ocp-Apim-Subscription-Key: {$this->subscriptionKey}"
        ]);
        curl_setopt($ch, CURLOPT_USERPWD, "{$this->apiUser}:{$this->apiKey}");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($ch);
        if (!$response) {
            throw new Exception('cURL error: ' . curl_error($ch));
        }
        curl_close($ch);

        $data = json_decode($response, true);
        if (!isset($data['access_token'])) {
            throw new Exception('Failed to get MoMo access token: ' . $response);
        }

        return $data['access_token'];
    }

    /**
     * Initiate payment request (C2B)
     */
    public function requestToPay(string $transactionId, string $amount, string $phone, string $currency = 'GHS'): array
    {
        $token = $this->getAccessToken();
        $url = $this->baseUrl . "/collection/v1_0/requesttopay";

        $payload = [
            "amount" => $amount,
            "currency" => $currency,
            "externalId" => $transactionId,
            "payer" => [
                "partyIdType" => "MSISDN",
                "partyId" => $phone
            ],
            "payerMessage" => "Payment Request",
            "payeeNote" => "Payment to merchant"
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer {$token}",
            "X-Reference-Id: {$transactionId}",
            "X-Target-Environment: sandbox",
            "Ocp-Apim-Subscription-Key: {$this->subscriptionKey}",
            "Content-Type: application/json"
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));

        $response = curl_exec($ch);
        if (!$response) {
            throw new Exception('cURL error: ' . curl_error($ch));
        }
        curl_close($ch);

        return ['status' => 'pending', 'transactionId' => $transactionId];
    }
}
