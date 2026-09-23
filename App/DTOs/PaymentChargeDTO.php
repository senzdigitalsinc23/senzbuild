<?php
declare(strict_types=1);

namespace App\DTOs;

class PaymentChargeDTO
{
    public function __construct(
        public readonly string $provider,
        public readonly string $phone,
        public readonly float $amount,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            provider: (string)($data['provider'] ?? ''),
            phone: (string)($data['phone'] ?? ''),
            amount: (float)($data['amount'] ?? 0.0),
        );
    }
}
