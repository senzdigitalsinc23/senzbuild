<?php
declare(strict_types=1);

namespace App\Models;

use Database\ORM\Model;

class RefreshToken extends Model
{
    protected static string $table = 'refresh_tokens';

    protected array $guarded = ['id'];

    public function user(): \Database\ORM\Relation
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    /**
     * Check if this token is still valid.
     */
    public function isValid(): bool
    {
        return $this->revoked_at === null && $this->expires_at > time();
    }

    /**
     * Revoke this token.
     */
    public function revoke(): void
    {
        $this->revoked_at = time();
        $this->save();
    }
}
