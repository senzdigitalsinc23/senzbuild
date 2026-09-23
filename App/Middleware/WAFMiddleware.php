<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Core\Response;

class WAFMiddleware
{
    // Blocked User Agents (scanners/tools)
    private array $badAgents = [
        'sqlmap', 'nikto', 'curb', 'nmap', 'nessus', 'acunetix', 'havij', 'w3af',
        'netsparker', 'dirbuster', 'gobuster', 'python-requests', 'zgrab'
    ];

    // Malicious Pattern Regexes
    private array $patterns = [
        // SQL Injection
        'sqli' => [
            '/(union\s+select)/i',
            '/(select\s+.*\s+from)/i',
            '/(union\s+all\s+select)/i',
            '/(insert\s+into)/i',
            '/(drop\s+table)/i',
            '/(truncate\s+table)/i',
            '/(exec\s+xp_)/i',
            '/(--)/', // comments
            '/(#)/',  // comments
            '/(\bOR\b\s+\d+=\d+)/i',
            '/(\bAND\b\s+\d+=\d+)/i'
        ],
        // XSS (Cross Site Scripting)
        'xss' => [
            '/(<script>)/i',
            '/(javascript:)/i',
            '/(onerror=)/i',
            '/(onload=)/i',
            '/(onclick=)/i',
            '/(onmouseover=)/i',
            '/(document\.cookie)/i',
            '/(alert\()/i'
        ],
        // Path Traversal / RFI
        'traversal' => [
            '/(\.\.\/)/',
            '/(\.\.\\\)/',
            '/(\/etc\/passwd)/i',
            '/(\/windows\/win.ini)/i',
            '/(cmd\.exe)/i',
            '/(bash -i)/i'
        ]
    ];

    public function handle($request = null, $response = null, $next = null)
    {
        // 1. Check User Agent
        if ($this->isBadUserAgent()) {
            return $this->blockRequest('Malicious User-Agent detected');
        }

        // 2. Scan Query Parameters ($request->get() or $_GET)
        if ($this->scanData($_GET, 'Query Param')) {
            return $this->blockRequest('Malicious pattern in Query Strings');
        }

        // 3. Scan Request Body (Parsed $_POST from JsonBodyParser)
        $postData = $_POST ?? [];
        // DEBUG LOG
        // file_put_contents(dirname(__DIR__, 2) . '/storage/logs/waf_debug.log', "POST Content: " . print_r($postData, true) . "\n", FILE_APPEND);

        if (!empty($postData) && $this->scanData($postData, 'Request Body')) {
             return $this->blockRequest('Malicious pattern in Request Body');
        }

        // 4. Scan URI
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        if ($this->scanString(urldecode($uri), 'URI')) {
             return $this->blockRequest('Malicious pattern in URI');
        }

        if ($next) {
            return $next($request, $response);
        }
        return $response;
    }

    private function isBadUserAgent(): bool
    {
        $agent = strtolower($_SERVER['HTTP_USER_AGENT'] ?? '');
        if (empty($agent)) return false;

        foreach ($this->badAgents as $bad) {
            if (str_contains($agent, $bad)) {
                return true;
            }
        }
        return false;
    }

    private function scanData(array $data, string $context): bool
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                if ($this->scanData($value, $context)) return true;
                continue;
            }
            if ($this->scanString((string)$value, $context)) {
                return true;
            }
        }
        return false;
    }

    private function scanString(string $input, string $context): bool
    {
        foreach ($this->patterns as $type => $regexes) {
            foreach ($regexes as $regex) {
                if (preg_match($regex, $input)) {
                    $this->logAttack($type, $context, $input);
                    return true;
                }
            }
        }
        return false;
    }

    private function blockRequest(string $reason)
    {
        $resp = new Response();
        $resp->setStatusCode(403);
        $resp->setHeader('Content-Type', 'application/json');
        $resp->setContent(json_encode([
            'success' => false,
            'code' => 403,
            'message' => 'Forbidden: Request blocked by WAF.',
            'debug' => $reason // Remove in prod if needed, useful for user
        ]));
        return $resp;
    }

    private function logAttack(string $type, string $context, string $payload)
    {
        $file = dirname(__DIR__, 2) . '/storage/logs/waf_attacks.log';
        if (!file_exists(dirname($file))) {
            mkdir(dirname($file), 0755, true);
        }

        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $time = date('Y-m-d H:i:s');
        $msg = "[$time] [WAF] Blocked $type attack from $ip in $context. Payload: " . substr($payload, 0, 100) . "\n";
        
        file_put_contents($file, $msg, FILE_APPEND);
    }
}
