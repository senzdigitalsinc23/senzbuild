<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Models\RefreshToken;
use PDO;

class RefreshTokenRepository
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? \App\Core\Database::getInstance()->getConnection();
    }

    /**
     * Create a new refresh token for a user.
     */
    public function create(string $userId, string $plainToken, ?string $ipAddress = null, ?string $userAgent = null): RefreshToken
    {
        $ttl = (int)($_ENV['JWT_REFRESH_TTL'] ?? 2592000); // 30 days default
        $expiresAt = time() + $ttl;
        $tokenHash = hash('sha256', $plainToken);

        $stmt = $this->db->prepare(
            "INSERT INTO refresh_tokens (user_id, token_hash, ip_address, user_agent, expires_at, created_at, updated_at)"
            . " VALUES (:user_id, :token_hash, :ip, :ua, :expires_at, NOW(), NOW())"
        );
        $stmt->execute([
            ':user_id'  => $userId,
            ':token_hash' => $tokenHash,
            ':ip'       => $ipAddress,
            ':ua'       => $userAgent,
            ':expires_at' => $expiresAt,
        ]);

        $token = new RefreshToken();
        $token->attributes = [
            'id'           => (int)$this->db->lastInsertId(),
            'user_id'      => $userId,
            'token_hash'   => $tokenHash,
            'ip_address'   => $ipAddress,
            'user_agent'   => $userAgent,
            'expires_at'   => $expiresAt,
            'revoked_at'   => null,
            'created_at'   => date('Y-m-d H:i:s'),
            'updated_at'   => date('Y-m-d H:i:s'),
        ];
        return $token;
    }

    /**
     * Find a valid token by its plain value.
     * Returns null if not found or invalid.
     */
    public function findByToken(string $plainToken): ?RefreshToken
    {
        $tokenHash = hash('sha256', $plainToken);

        $stmt = $this->db->prepare(
            "SELECT * FROM refresh_tokens WHERE token_hash = :hash AND revoked_at IS NULL AND expires_at > :now LIMIT 1"
        );
        $stmt->execute([':hash' => $tokenHash, ':now' => time()]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        $token = new RefreshToken();
        $token->attributes = $row;
        return $token;
    }

    /**
     * Revoke all tokens for a user (logout from all devices).
     */
    public function revokeAllForUser(string $userId): int
    {
        $stmt = $this->db->prepare(
            "UPDATE refresh_tokens SET revoked_at = :now WHERE user_id = :user_id AND revoked_at IS NULL"
        );
        $stmt->execute([':now' => time(), ':user_id' => $userId]);
        return $stmt->rowCount();
    }

    /**
     * Revoke a single token by its plain value.
     */
    public function revokeByToken(string $plainToken): bool
    {
        $tokenHash = hash('sha256', $plainToken);

        $stmt = $this->db->prepare(
            "UPDATE refresh_tokens SET revoked_at = :now WHERE token_hash = :hash AND revoked_at IS NULL LIMIT 1"
        );
        $stmt->execute([':now' => time(), ':hash' => $tokenHash]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Revoke all expired tokens older than $olderThan seconds.
     */
    public function pruneExpired(int $olderThan = 86400): int
    {
        $cutoff = time() - $olderThan;
        $stmt = $this->db->prepare(
            "DELETE FROM refresh_tokens WHERE expires_at < :cutoff AND (revoked_at IS NULL OR revoked_at < :cutoff)"
        );
        $stmt->execute([':cutoff' => $cutoff]);
        return $stmt->rowCount();
    }

    /**
     * Get all active tokens for a user (for "remembered devices" list).
     */
    public function findByUser(string $userId): array
    {
        $stmt = $this->db->prepare(
            "SELECT id, user_id, ip_address, user_agent, expires_at, created_at"
            . " FROM refresh_tokens WHERE user_id = :user_id AND revoked_at IS NULL AND expires_at > :now"
            . " ORDER BY created_at DESC"
        );
        $stmt->execute([':user_id' => $userId, ':now' => time()]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
