<?php
declare(strict_types=1);
// App\Middleware\BruteForceLockoutMiddleware.php
namespace App\Middleware;

class BruteForceLockoutMiddleware
{
    private int $maxAttempts;
    private int $lockoutSeconds;
    private string $prefix;

    public function __construct()
    {
        $this->maxAttempts    = (int)($_ENV['BRUTE_FORCE_MAX'] ?? 5); // attempts
        $this->lockoutSeconds = (int)($_ENV['BRUTE_FORCE_LOCKOUT'] ?? 900); // 15 min
        $this->prefix         = 'bruteforce:';
    }

    public function handle($request = null, $response = null, $next = null)
    {
        $ip   = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $key  = $this->prefix . hash('sha256', $ip);
        $now  = time();
        $data = $this->get($key) ?? ['count' => 0, 'expires' => 0];

        if ($data['expires'] > $now) {
            $resp = $response ?? new \App\Core\Response();
            $resp->setStatusCode(429);
            $resp->setHeader('Content-Type', 'application/json');
            $resp->setContent(json_encode(['error' => 'Too many failed login attempts. Try again later.']));
            return $resp;
        }
        if ($next) return $next($request, $response);
        return $response;
    }

    public function recordFailure(): void
    {
        $ip   = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $key  = $this->prefix . hash('sha256', $ip);
        $now  = time();
        $data = $this->get($key) ?? ['count' => 0, 'expires' => 0];
        $data['count']++;
        if ($data['count'] >= $this->maxAttempts) {
            $data['expires'] = $now + $this->lockoutSeconds;
            $data['count'] = 0;
        }
        $this->set($key, $data, $this->lockoutSeconds);
    }

    public function clear(): void
    {
        $ip   = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $key  = $this->prefix . hash('sha256', $ip);
        $this->set($key, ['count' => 0, 'expires' => 0], $this->lockoutSeconds);
    }

    private function apcuEnabled(): bool
    {
        return function_exists('apcu_enabled') && apcu_enabled();
    }

    private function get(string $key): ?array
    {
        if ($this->apcuEnabled()) {
            $ok = apcu_fetch($key, $success);
            return $success ? $ok : null;
        }
        $file = sys_get_temp_dir() . '/' . $key;
        if (!file_exists($file)) return null;
        $json = file_get_contents($file);
        return $json ? json_decode($json, true) : null;
    }

    private function set(string $key, array $value, int $ttl): void
    {
        if ($this->apcuEnabled()) {
            apcu_store($key, $value, $ttl);
        } else {
            $file = sys_get_temp_dir() . '/' . $key;
            file_put_contents($file, json_encode($value));
        }
    }
}
