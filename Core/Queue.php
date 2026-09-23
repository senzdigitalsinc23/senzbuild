<?php
declare(strict_types=1);

namespace App\Core;

class Queue
{
    protected string $connection;
    protected string $queue;
    protected Logger $logger;

    public function __construct(Logger $logger, ?string $connection = null)
    {
        $this->logger = $logger;
        $this->connection = $connection ?? Config::get('queue.default', 'database');
        $this->queue = Config::get("queue.connections.{$this->connection}.queue", 'default');
    }

    /**
     * Dispatch a job to the queue.
     */
    public function dispatch(string $jobClass, array $data = []): int
    {
        if ($this->connection === 'sync') {
            $this->dispatchSync($jobClass, $data);
            return 0;
        }

        $job = $this->resolveJob($jobClass, $data);
        $maxAttempts = method_exists($job, 'maxTries') ? $job->maxTries() : Config::get('queue.connections.' . $this->connection . '.min_attempts', 3);
        $backoff = method_exists($job, 'backoff') ? $job->backoff() : 'exponential';

        $payload = json_encode([
            'job_class'    => $jobClass,
            'data'         => $data,
            'max_attempts' => $maxAttempts,
            'backoff'      => $backoff,
            'dispatched_at'=> time(),
        ], JSON_THROW_ON_ERROR);

        $db = Database::getInstance()->getConnection();
        $sql = "INSERT INTO " . Config::get("queue.connections.{$this->connection}.table", 'queue_jobs')
             . " (job_class, queue, payload, attempts, max_attempts, reserved_at, available_at, status, created_at, updated_at)"
             . " VALUES (:job_class, :queue, :payload, 0, :max_attempts, NULL, :available_at, 'pending', NOW(), NOW())";

        $stmt = $db->prepare($sql);
        $stmt->execute([
            ':job_class'  => $jobClass,
            ':queue'      => $this->queue,
            ':payload'    => $payload,
            ':max_attempts' => $maxAttempts,
            ':available_at' => time(),
        ]);

        $jobId = (int)$db->lastInsertId();
        $this->logger->info("Job dispatched: {$jobClass} (ID: {$jobId}, queue: {$this->queue})");

        return $jobId;
    }

    /**
     * Dispatch a job after a delay (in seconds).
     */
    public function dispatchAfter(string $jobClass, array $data = [], int $delaySeconds = 0): int
    {
        if ($this->connection === 'sync') {
            $this->dispatchSync($jobClass, $data);
            return 0;
        }

        $job = $this->resolveJob($jobClass, $data);
        $maxAttempts = method_exists($job, 'maxTries') ? $job->maxTries() : Config::get('queue.connections.' . $this->connection . '.min_attempts', 3);
        $backoff = method_exists($job, 'backoff') ? $job->backoff() : 'exponential';

        $availableAt = time() + max(0, $delaySeconds);

        $payload = json_encode([
            'job_class'    => $jobClass,
            'data'         => $data,
            'max_attempts' => $maxAttempts,
            'backoff'      => $backoff,
            'dispatched_at'=> time(),
        ], JSON_THROW_ON_ERROR);

        $db = Database::getInstance()->getConnection();
        $table = Config::get("queue.connections.{$this->connection}.table", 'queue_jobs');
        $sql = "INSERT INTO {$table} (job_class, queue, payload, attempts, max_attempts, reserved_at, available_at, status, created_at, updated_at)"
             . " VALUES (:job_class, :queue, :payload, 0, :max_attempts, NULL, :available_at, 'pending', NOW(), NOW())";

        $stmt = $db->prepare($sql);
        $stmt->execute([
            ':job_class'    => $jobClass,
            ':queue'        => $this->queue,
            ':payload'      => $payload,
            ':max_attempts' => $maxAttempts,
            ':available_at' => $availableAt,
        ]);

        $jobId = (int)$db->lastInsertId();
        $this->logger->info("Delayed job dispatched: {$jobClass} (ID: {$jobId}, available at: {$availableAt})");

        return $jobId;
    }

    /**
     * Dispatch a job to a specific queue.
     */
    public function onQueue(string $jobClass, array $data = [], string $queue = 'default'): int
    {
        $originalQueue = $this->queue;
        $this->queue = $queue;
        $result = $this->dispatch($jobClass, $data);
        $this->queue = $originalQueue;
        return $result;
    }

    /**
     * Dispatch synchronously (immediate execution).
     */
    protected function dispatchSync(string $jobClass, array $data): void
    {
        try {
            $instance = new $jobClass(...array_values($data));
            $instance->handle();
        } catch (\Throwable $e) {
            $this->logger->error("Sync job failed: {$jobClass} - " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Resolve a job instance (for inspection only — does not execute).
     */
    protected function resolveJob(string $jobClass, array $data): object
    {
        if (!class_exists($jobClass)) {
            throw new \InvalidArgumentException("Job class {$jobClass} does not exist");
        }
        return new $jobClass(...array_values($data));
    }

    /**
     * Get the number of pending jobs.
     */
    public function pendingCount(?string $queue = null): int
    {
        $q = $queue ?? $this->queue;
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT COUNT(*) as cnt FROM " . Config::get("queue.connections.{$this->connection}.table", 'queue_jobs')
                           . " WHERE queue = :queue AND status IN ('pending', 'reserved') AND available_at <= :now");
        $stmt->execute([':queue' => $q, ':now' => time()]);
        return (int)$stmt->fetchColumn();
    }

    /**
     * Get the number of failed jobs.
     */
    public function failedCount(): int
    {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->query("SELECT COUNT(*) FROM queue_failed_jobs");
        return (int)$stmt->fetchColumn();
    }
}
