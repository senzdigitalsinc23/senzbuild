<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Redis Queue Driver — jobs stored in Redis for high-throughput processing.
 *
 * Requires: predis/predis or phpredis extension
 * Config: queue.connections.redis.driver = 'redis'
 */
class RedisQueue implements \JsonSerializable
{
    protected ?object $redis = null;
    protected string $connection = 'default';
    protected Logger $logger;

    public function __construct(Logger $logger, string $connection = 'default')
    {
        $this->logger = $logger;
        $this->connection = $connection;

        $host = $_ENV['REDIS_HOST'] ?? '127.0.0.1';
        $port = (int)($_ENV['REDIS_PORT'] ?? 6379);

        if (extension_loaded('redis')) {
            $this->redis = new \Redis();
            $this->redis->connect($host, $port, 2.5);
        } elseif (class_exists('\Predis\Client')) {
            $this->redis = new \Predis\Client(["scheme" => "tcp", "host" => $host, "port" => $port]);
        }
    }

    public function isConnected(): bool
    {
        return $this->redis !== null;
    }

    /**
     * Push a job onto the Redis queue.
     */
    public function push(string $jobClass, array $data = [], string $queue = 'default'): int
    {
        if (!$this->redis) {
            throw new \RuntimeException('Redis queue driver is not connected');
        }

        $payload = json_encode([
            'job_class'    => $jobClass,
            'data'         => $data,
            'max_attempts' => $data['max_attempts'] ?? 3,
            'dispatched_at'=> time(),
            'queue'        => $queue,
        ], JSON_THROW_ON_ERROR);

        $key = "queue:{$queue}";
        $this->redis->lPush($key, $payload);

        // Fire event for listeners
        $this->redis->publish("queue:{$queue}:messages", $payload);

        $this->logger->info("Redis job pushed: {$jobClass} to queue:{$queue}");
        return (int)$this->redis->lLen($key);
    }

    /**
     * Pop a job from the queue.
     */
    public function pop(string $queue = 'default'): ?array
    {
        if (!$this->redis) {
            return null;
        }

        $key = "queue:{$queue}";
        $payload = $this->redis->rPop($key);

        if ($payload === null) {
            return null;
        }

        $data = json_decode($payload, true);
        if ($data === null) {
            return null;
        }

        return $data;
    }

    /**
     * Get queue length.
     */
    public function size(string $queue = 'default'): int
    {
        if (!$this->redis) {
            return 0;
        }
        return (int)$this->redis->lLen("queue:{$queue}");
    }

    /**
     * Peek at the next job without removing it.
     */
    public function peek(string $queue = 'default'): ?array
    {
        if (!$this->redis) {
            return null;
        }
        $payload = $this->redis->lIndex("queue:{$queue}", -1);
        if ($payload === false) {
            return null;
        }
        return json_decode($payload, true);
    }

    public function jsonSerialize(): array
    {
        return ['driver' => 'redis', 'connected' => $this->redis !== null];
    }
}
