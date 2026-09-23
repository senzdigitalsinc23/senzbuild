<?php
declare(strict_types=1);

namespace App\DTOs;

class PaymentWebhookDTO
{
    public function __construct(
        public readonly string $transactionId,
        public readonly string $status,
        public readonly string $reference,
        public readonly string $reason,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            transactionId: (string)($data['transactionId'] ?? ''),
            status: (string)($data['status'] ?? 'failed'),
            reference: (string)($data['reference'] ?? ''),
            reason: (string)($data['reason'] ?? ''),
        );
    }
}
