<?php
declare(strict_types=1);

namespace App\CLI;

use PDO;

/**
 * CLI command to browse and search audit logs.
 *
 * Usage:
 *   php bin/console audit:logs                 # last 20 entries
 *   php bin/console audit:logs --limit 50      # last 50 entries
 *   php bin/console audit:logs --module sales_return
 *   php bin/console audit:logs --user-id u-123
 *   php bin/console audit:logs --since 24h     # last 24 hours
 *   php bin/console audit:logs --severity critical
 *   php bin/console audit:logs --export        # export as CSV
 */
class AuditLogs extends Command
{
    protected string $name = 'audit:logs';
    protected string $description = 'Browse and search audit log entries.';

    private PDO $db;

    public function handle(array $args): void
    {
        $this->db = \App\Core\Database::getInstance()->getConnection();

        $limit       = (int)($args['--limit'] ?? 20);
        $module      = $args['--module'] ?? null;
        $userId      = $args['--user-id'] ?? null;
        $severity    = $args['--severity'] ?? null;
        $entityId    = $args['--entity-id'] ?? null;
        $since       = $args['--since'] ?? null;
        $export      = in_array('--export', $args, true);
        $search      = $args[0] ?? null; // positional arg as search term

        // Parse --since (e.g. "24h", "7d", "1h30m")
        $sinceSeconds = $this->parseSince($since);

        $where = [];
        $params = [];

        if ($module) {
            $where[] = 'module = :module';
            $params[':module'] = $module;
        }
        if ($userId) {
            $where[] = 'user_id = :user_id';
            $params[':user_id'] = $userId;
        }
        if ($severity) {
            $where[] = 'severity = :severity';
            $params[':severity'] = $severity;
        }
        if ($entityId) {
            $where[] = 'entity_id = :entity_id';
            $params[':entity_id'] = $entityId;
        }
        if ($sinceSeconds > 0) {
            $where[] = 'created_at >= DATE_SUB(NOW(), INTERVAL :seconds SECOND)';
            $params[':seconds'] = $sinceSeconds;
        }
        if ($search) {
            $where[] = '(description LIKE :search OR entity_name LIKE :search)';
            $params[':search'] = "%{$search}%";
        }

        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        // Count total matching
        $countSql = "SELECT COUNT(*) FROM audit_logs {$whereSql}";
        $stmt = $this->db->prepare($countSql);
        $stmt->execute($params);
        $total = (int)$stmt->fetchColumn();

        if ($export) {
            $this->exportCsv($whereSql, $params, $limit);
            return;
        }

        if ($total === 0) {
            $this->info("No audit log entries found.");
            return;
        }

        // Fetch entries
        $selectSql = "SELECT id, user_id, user_name, module, action, entity_id, entity_name, description, ip_address, severity, created_at"
                   . " FROM audit_logs {$whereSql}"
                   . " ORDER BY created_at DESC LIMIT :limit";
        $stmt = $this->db->prepare($selectSql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Print header
        echo "\n";
        echo "  Audits (showing {$limit} of {$total})\n";
        echo str_repeat('-', 120) . "\n";
        printf("  %-8s | %-12s | %-10s | %-8s | %-20s | %s\n",
            'DATE', 'MODULE', 'ACTION', 'SEV', 'ENTITY', 'DESCRIPTION');
        echo str_repeat('-', 120) . "\n";

        foreach ($logs as $log) {
            $date = $log['created_at'] ? date('Y-m-d H:i', strtotime($log['created_at'])) : 'N/A';
            $sev  = $this->sevColor($log['severity'] ?? 'info');
            printf("  %-8s | %-12s | %-10s | %-8s | %-20s | %s\n",
                $date,
                $log['module'] ?? '',
                $log['action'] ?? '',
                $sev . strtoupper($log['severity'] ?? 'info') . "\033[0m",
                ($log['entity_name'] ?? $log['entity_id'] ?? '') ,
                mb_substr($log['description'] ?? '', 0, 40)
            );
        }

        echo str_repeat('-', 120) . "\n";
        echo "  Total: {$total} entries\n\n";
    }

    /**
     * Export audit logs to CSV.
     */
    private function exportCsv(string $whereSql, array $params, int $limit): void
    {
        $selectSql = "SELECT id, user_id, user_name, module, action, entity_id, entity_name, description, ip_address, severity, created_at"
                   . " FROM audit_logs {$whereSql}"
                   . " ORDER BY created_at DESC LIMIT :limit";
        $stmt = $this->db->prepare($selectSql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $file = storage_path('logs/audit_export_' . date('Ymd_His') . '.csv');
        $fp = fopen($file, 'w');
        fputcsv($fp, ['id', 'user_id', 'user_name', 'module', 'action', 'entity_id', 'entity_name', 'description', 'ip_address', 'severity', 'created_at']);
        foreach ($logs as $log) {
            fputcsv($fp, $log);
        }
        fclose($fp);

        $this->success("Exported {$limit} entries to: {$file}");
    }

    /**
     * Parse a --since value like "24h", "7d", "1h30m" into seconds.
     */
    private function parseSince(?string $value): int
    {
        if (!$value) return 0;

        $total = 0;
        // Match patterns like 24h, 7d, 1h30m, 30m, 60s
        if (preg_match('/^(\d+)\s*d$/i', $value, $m)) {
            $total = (int)$m[1] * 86400;
        } elseif (preg_match('/^(\d+)\s*h$/i', $value, $m)) {
            $total = (int)$m[1] * 3600;
        } elseif (preg_match('/^(\d+)\s*m$/i', $value, $m)) {
            $total = (int)$m[1] * 60;
        } elseif (preg_match('/^(\d+)\s*s$/i', $value, $m)) {
            $total = (int)$m[1];
        } elseif (preg_match('/^(\d+)h(\d+)m$/i', $value, $m)) {
            $total = (int)$m[1] * 3600 + (int)$m[2] * 60;
        }

        return $total;
    }

    private function sevColor(string $sev): string
    {
        return match (strtolower($sev)) {
            'critical' => "\033[41;97m", // red bg white text
            'warning'  => "\033[43;30m", // yellow bg black text
            default    => "\033[44;97m", // blue bg white text
        };
    }
}

/**
 * Helper to get storage path.
 */
function storage_path(string $path = ''): string
{
    return dirname(__DIR__, 2) . '/storage/' . $path;
}
