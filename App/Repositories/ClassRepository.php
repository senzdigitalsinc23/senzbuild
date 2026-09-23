<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Core\Cache;
use PDO;
use PDOException;

class ClassRepository
{
    private PDO $db;
    private Cache $cache;
    private const TABLE = 'classes';

    public function __construct(?PDO $db = null, ?Cache $cache = null)
    {
        $this->db = $db ?? throw new \RuntimeException('PDO connection required');
        $this->cache = $cache ?? new Cache();
    }

    /**
     * Check which class IDs exist in the database (batch query).
     *
     * @param int[] $ids
     * @return array<int, bool> Only includes IDs that exist in the database
     */
    public function existsBatch(array $ids): array
    {
        if (empty($ids)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $sql = "SELECT id FROM " . static::TABLE . " WHERE id IN ($placeholders)";

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute($ids);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $result = [];
            foreach ($rows as $row) {
                $result[(int)$row['id']] = true;
            }
            return $result;
        } catch (PDOException $e) {
            return [];
        }
    }
}
