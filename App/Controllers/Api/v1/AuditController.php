<?php
declare(strict_types=1);

namespace App\Controllers\Api\v1;

use App\Core\Request;
use App\Core\Response;
use App\Core\Traits\JsonResponseTrait;
use PDO;

/**
 * Audit log API controller.
 *
 * Endpoints:
 *   GET  /api/v1/audit/logs          — list recent audit entries
 *   GET  /api/v1/audit/logs/{id}     — single entry
 *   GET  /api/v1/audit/logs/export   — CSV export
 */
class AuditController
{
    use JsonResponseTrait;

    private PDO $db;
    private int $defaultLimit;
    private int $maxLimit;

    public function __construct(?PDO $db = null, int $defaultLimit = 20, int $maxLimit = 200)
    {
        $this->db           = $db ?? \App\Core\Database::getInstance()->getConnection();
        $this->defaultLimit = $defaultLimit;
        $this->maxLimit     = $maxLimit;
    }

    /**
     * GET /api/v1/audit/logs
     */
    public function index(Request $request, Response $response): Response
    {
        $limit     = min((int)($request->getQuery('limit') ?? $this->defaultLimit), $this->maxLimit);
        $module    = $request->getQuery('module');
        $userId    = $request->getQuery('user_id');
        $severity  = $request->getQuery('severity');
        $entityId  = $request->getQuery('entity_id');
        $search    = $request->getQuery('search');
        $since     = $request->getQuery('since');

        $where = [];
        $params = [];

        if ($module)    { $where[] = 'module = :module';    $params[':module'] = $module; }
        if ($userId)    { $where[] = 'user_id = :user_id';  $params[':user_id'] = $userId; }
        if ($severity)  { $where[] = 'severity = :severity'; $params[':severity'] = $severity; }
        if ($entityId)  { $where[] = 'entity_id = :entity_id'; $params[':entity_id'] = $entityId; }
        if ($search)    { $where[] = '(description LIKE :search OR entity_name LIKE :search)'; $params[':search'] = "%{$search}%"; }
        if ($since)     {
            $seconds = $this->parseSince($since);
            if ($seconds > 0) {
                $where[] = 'created_at >= DATE_SUB(NOW(), INTERVAL :seconds SECOND)';
                $params[':seconds'] = $seconds;
            }
        }

        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        // Count
        $countStmt = $this->db->prepare("SELECT COUNT(*) FROM audit_logs {$whereSql}");
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        // Fetch
        $selectSql = "SELECT id, user_id, user_name, module, action, entity_id, entity_name, description, ip_address, severity, created_at"
                   . " FROM audit_logs {$whereSql} ORDER BY created_at DESC LIMIT :limit";
        $stmt = $this->db->prepare($selectSql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        $logs = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        return $this->json($response, 200, [
            'success'  => true,
            'data'     => $logs,
            'pagination' => [
                'total'     => $total,
                'limit'     => $limit,
                'has_more'  => ($total - count($logs)) > 0,
            ],
        ]);
    }

    /**
     * GET /api/v1/audit/logs/{id}
     */
    public function show(Request $request, Response $response, array $params): Response
    {
        $id = $params['id'] ?? null;
        if (!$id) {
            return $this->json($response, 400, ['success' => false, 'message' => 'ID is required']);
        }

        $stmt = $this->db->prepare("SELECT * FROM audit_logs WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $id]);
        $log = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$log) {
            return $this->json($response, 404, ['success' => false, 'message' => 'Audit log not found']);
        }

        return $this->json($response, 200, ['success' => true, 'data' => $log]);
    }

    /**
     * GET /api/v1/audit/logs/export
     */
    public function export(Request $request, Response $response): Response
    {
        // Reuse index logic but return CSV
        $limit = min((int)($request->getQuery('limit') ?? 500), 5000);
        $module    = $request->getQuery('module');
        $userId    = $request->getQuery('user_id');
        $severity  = $request->getQuery('severity');

        $where = [];
        $params = [];
        if ($module)    { $where[] = 'module = :module';    $params[':module'] = $module; }
        if ($userId)    { $where[] = 'user_id = :user_id';  $params[':user_id'] = $userId; }
        if ($severity)  { $where[] = 'severity = :severity'; $params[':severity'] = $severity; }

        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $stmt = $this->db->prepare(
            "SELECT id, user_id, user_name, module, action, entity_id, entity_name, description, ip_address, severity, created_at"
            . " FROM audit_logs {$whereSql} ORDER BY created_at DESC LIMIT :limit"
        );
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        $logs = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // Build CSV
        $csv = "id,user_id,user_name,module,action,entity_id,entity_name,description,ip_address,severity,created_at\n";
        foreach ($logs as $log) {
            $csv .= implode(',', array_map(fn($v) => '"' . str_replace('"', '""', (string)$v) . '"', $log)) . "\n";
        }

        $response->setHeader('Content-Type', 'text/csv');
        $response->setHeader('Content-Disposition', 'attachment; filename="audit_export_' . date('Ymd_His') . '.csv"');
        $response->setContent($csv);
        return $response;
    }

    private function parseSince(?string $value): int
    {
        if (!$value) return 0;
        if (preg_match('/^(\d+)\s*d$/i', $value, $m)) return (int)$m[1] * 86400;
        if (preg_match('/^(\d+)\s*h$/i', $value, $m)) return (int)$m[1] * 3600;
        if (preg_match('/^(\d+)\s*m$/i', $value, $m)) return (int)$m[1] * 60;
        return 0;
    }
}
