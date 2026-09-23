<?php
declare(strict_types=1);

namespace App\DTOs;

/**
 * DTO for authentication response (login / register).
 */
class AuthResponseDTO
{
    public function __construct(
        public readonly UserDTO  $user,
        public readonly string   $token,
        public readonly ?string  $refreshToken,
        public readonly int      $expiresIn,
        public readonly bool     $requires2fa = false,
    ) {}

    public function toArray(): array
    {
        $result = [
            'user'         => $this->user->toArrayWithoutPassword(),
            'token'        => $this->token,
            'token_type'   => 'Bearer',
            'expires_in'   => $this->expiresIn,
            'requires_2fa' => $this->requires2fa,
        ];
        if ($this->refreshToken !== null) {
            $result['refresh_token'] = $this->refreshToken;
        }
        return $result;
    }
}
