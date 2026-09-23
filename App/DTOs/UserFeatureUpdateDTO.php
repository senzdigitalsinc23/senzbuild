<?php
declare(strict_types=1);

namespace App\DTOs;

class UserFeatureUpdateDTO
{
    public function __construct(
        public readonly ?array $features = null,
        public readonly ?string $grantedBy = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            features: $data['features'] ?? null,
            grantedBy: $data['granted_by'] ?? null,
        );
    }
}
