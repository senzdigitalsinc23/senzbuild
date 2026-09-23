<?php
// app/Repositories/ApiKeyRepository.php
namespace Repositories;

use App\Core\Database;
use App\Core\DB;
use DateTimeImmutable;

class ApiKeyRepo
{
    public function find(string $key): ?array
    {
        // 1) DB lookup if table exists
        try {
            $pdo = Database::getInstance()->getConnection();
            $stmt = $pdo->prepare('SELECT key_value, owner, scopes, active, expires_at FROM api_keys WHERE key_value = ? LIMIT 1');
            $stmt->execute([$key]);
            $row = $stmt->fetch();
            if ($row) {
                // cast/normalize
                if (isset($row['scopes']) && is_string($row['scopes'])) {
                    $row['scopes'] = json_decode($row['scopes'], true) ?: [];
                }
                return $row;
            }
        } catch (\Throwable $e) {
            // table may not exist yet; fall back to env
        }

        // 2) .env fallback (comma-separated)
        $env = $_ENV['API_KEYS'] ?? '';
        $keys = array_filter(array_map('trim', explode(',', $env)));
        if (in_array($key, $keys, true)) {
            return [
                'key_value' => $key,
                'owner'     => null,
                'scopes'    => [],
                'active'    => 1,
                'expires_at'=> null
            ];
        }
        return null;
    }

    public function isValid(array $record): bool
    {
        if (empty($record['active'])) return false;
        if (!empty($record['expires_at'])) {
            $exp = new DateTimeImmutable($record['expires_at']);
            if ($exp < new DateTimeImmutable('now')) return false;
        }
        return true;
    }
}
