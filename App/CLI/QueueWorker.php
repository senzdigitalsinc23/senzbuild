<?php
declare(strict_types=1);

namespace App\CLI;

use App\Core\Config;
use App\Core\Database;
use PDO;

class QueueWorker extends Command
{
    protected string $name = 'queue:work';
    protected string $description = 'Process pending jobs from the queue with retry and DLQ support.';

    private PDO $db;
    private string $connection;
    private string $queue;
    private string $table;
    private int $retryAfter;
    private int $workerTimeout;
    private int $workerSleep;
    private int $maxJobs;
    private int $jobsProcessed;
    private bool $shouldStop = false;
    private ?int $currentJobId = null; // tracks the job currently being processed
    private array $shutdownMetrics = [];

    public function __construct(array $args = [])
    {
        parent::__construct($args);
        $this->connection = Config::get('queue.default', 'database');
        $this->queue = Config::get("queue.connections.{$this->connection}.queue", 'default');
        $this->table = Config::get("queue.connections.{$this->connection}.table", 'queue_jobs');
        $this->retryAfter = Config::get("queue.connections.{$this->connection}.retry_after", 90);
        $this->workerTimeout = Config::get('queue.workers.timeout', 60);
        $this->workerSleep = Config::get('queue.workers.sleep', 3);
        $this->maxJobs = Config::get('queue.workers.max_jobs', 0);
        $this->jobsProcessed = 0;

        register_shutdown_function([$this, 'onShutdown']);
        pcntl_signal(SIGTERM, [$this, 'onSignal']);
        pcntl_signal(SIGINT,  [$this, 'onSignal']);
    }

    public function handle(array $args): void
    {
        $once = in_array('--once', $args, true);
        $queue = null;
        foreach ($args as $i => $arg) {
            if ($arg === '--queue' && isset($args[$i + 1])) {
                $queue = $args[$i + 1];
            }
        }
        if ($queue) {
            $this->queue = $queue;
        }

        $this->info("Queue worker started (connection: {$this->connection}, queue: {$this->queue})");
        $startTime = time();

        while (!$this->shouldStop) {
            // Check timeout
            if ($this->workerTimeout > 0 && (time() - $startTime) >= $this->workerTimeout) {
                $this->info("Worker timeout reached. Stopping...");
                break;
            }

            // Check max jobs
            if ($this->maxJobs > 0 && $this->jobsProcessed >= $this->maxJobs) {
                $this->info("Max jobs ({$this->maxJobs}) reached. Stopping...");
                break;
            }

            $job = $this->reserveJob();
            if ($job) {
                $this->currentJobId = (int)$job['id'];
                $this->processJob($job);
                $this->currentJobId = null;
                usleep(100000);
            } else {
                if ($once) {
                    break;
                }
                sleep($this->workerSleep);
            }
        }

        $this->info("Queue worker stopped. Jobs processed: {$this->jobsProcessed}");
    }

    /**
     * Graceful shutdown: release any stuck reserved jobs and report metrics.
     */
    public function onShutdown(): void
    {
        // Release any job that was reserved but never completed (e.g. worker killed mid-job)
        if ($this->currentJobId !== null) {
            $this->releaseStuckJob($this->currentJobId);
        }

        // Release any jobs reserved longer than retry_after
        $this->releaseStaleJobs();

        // Log shutdown metrics
        $this->info("Shutdown metrics: processed={$this->jobsProcessed}, "
            . "running=" . $this->getReservedCount()
            . ", failed=" . $this->getFailedCount());
    }

    /**
     * Reserve the next available job (atomic lock).
     */
    private function reserveJob(): ?array
    {
        $now = time();
        $stmt = $this->db->prepare(
            "UPDATE {$this->table} SET status = 'reserved', reserved_at = :reserved_at, updated_at = NOW()"
            . " WHERE id = ("
            . "  SELECT id FROM (SELECT id FROM {$this->table}"
            . "   WHERE queue = :queue AND status = 'pending' AND available_at <= :now"
            . "   ORDER BY created_at ASC LIMIT 1"
            . "  ) t"
            . ")"
        );
        $stmt->execute([':queue' => $this->queue, ':now' => $now, ':reserved_at' => $now]);

        if ($stmt->rowCount() === 0) {
            return null;
        }

        $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE reserved_at = :reserved_at ORDER BY created_at ASC LIMIT 1");
        $stmt->execute([':reserved_at' => $now]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Process a single job with retry logic and DLQ.
     */
    private function processJob(array $job): void
    {
        $jobId = $job['id'];
        $jobClass = $job['job_class'];

        $this->info("Processing job ID: {$jobId} ({$jobClass})");

        try {
            $payload = json_decode($job['payload'], true);
            if (!$payload) {
                throw new \Exception("Invalid job payload");
            }

            $maxAttempts = (int)($payload['max_attempts'] ?? $job['max_attempts'] ?? 3);
            $backoff = $payload['backoff'] ?? 'exponential';

            if (!class_exists($jobClass)) {
                throw new \Exception("Job class {$jobClass} not found");
            }

            $instance = new $jobClass(...array_values($payload['data'] ?? []));
            $reflection = new \ReflectionMethod($instance, 'handle');
            $params = $reflection->getParameters();
            $args = [];
            foreach ($params as $param) {
                $name = $param->getName();
                $args[] = $payload['data'][$name] ?? null;
            }
            $reflection->invokeArgs($instance, $args);

            $this->markCompleted($jobId);
            $this->jobsProcessed++;
            $this->success("Job ID: {$jobId} completed.");

        } catch (\Throwable $e) {
            $this->handleJobFailure($job, $e);
        }
    }

    /**
     * Handle a failed job: retry with backoff or send to DLQ.
     */
    private function handleJobFailure(array $job, \Throwable $e): void
    {
        $jobId = $job['id'];
        $attempts = (int)$job['attempts'] + 1;
        $maxAttempts = (int)($job['max_attempts'] ?? 3);
        $payload = json_decode($job['payload'], true);
        $backoff = $payload['backoff'] ?? 'exponential';

        $this->error("Job ID: {$jobId} failed (attempt {$attempts}/{$maxAttempts}): " . $e->getMessage());
        $this->logJobFailure($job, $e);

        if ($attempts >= $maxAttempts) {
            // Move to Dead Letter Queue
            $this->moveToDeadLetterQueue($job, $e);
            $this->markFailed($jobId, (string)$e);
            $this->warning("Job ID: {$jobId} moved to DLQ after {$maxAttempts} attempts.");
            return;
        }

        // Calculate delay
        $delay = $this->calculateBackoff($backoff, $attempts, $maxAttempts);
        $nextRun = time() + $delay;

        // Release the job for re-processing
        $this->releaseJob($jobId, $attempts, $nextRun);
        $this->warning("Job ID: {$jobId} released for retry #{$attempts} in {$delay}s.");
    }

    /**
     * Calculate retry delay using exponential or linear backoff.
     */
    private function calculateBackoff(string|float|int|array $backoff, int $attempt, int $maxAttempts): int
    {
        if (is_array($backoff)) {
            $index = min($attempt - 1, count($backoff) - 1);
            return (int)$backoff[$index];
        }

        if ($backoff === 'linear') {
            return $attempt * 10;
        }

        // Default: exponential
        $base = (int)Config::get('queue.connections.' . $this->connection . '.retry_after', 90);
        $delay = (int)min($base * pow(2, $attempt - 1), 3600);
        return $delay;
    }

    /**
     * Mark job as completed.
     */
    private function markCompleted(int $jobId): void
    {
        $this->db->prepare("UPDATE {$this->table} SET status = 'completed', updated_at = NOW() WHERE id = ?")
                 ->execute([$jobId]);
    }

    /**
     * Mark job as failed.
     */
    private function markFailed(int $jobId, string $errorMessage): void
    {
        $this->db->prepare("UPDATE {$this->table} SET status = 'failed', error_message = ?, updated_at = NOW() WHERE id = ?")
                 ->execute([$errorMessage, $jobId]);
    }

    /**
     * Release a job for retry with new available_at time.
     */
    private function releaseJob(int $jobId, int $attempts, int $availableAt): void
    {
        $this->db->prepare(
            "UPDATE {$this->table} SET status = 'pending', reserved_at = NULL, attempts = ?, available_at = ?, updated_at = NOW() WHERE id = ?"
        )->execute([$attempts, $availableAt, $jobId]);
    }

    /**
     * Move a job to the dead letter queue.
     */
    private function moveToDeadLetterQueue(array $job, \Throwable $e): void
    {
        $uuid = bin2hex(random_bytes(16));
        $exception = get_class($e) . ': ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine();

        try {
            $this->db->prepare(
                "INSERT INTO queue_failed_jobs (uuid, connection, queue, payload, exception, failed_at)"
                . " VALUES (:uuid, :connection, :queue, :payload, :exception, NOW())"
            )->execute([
                ':uuid'       => $uuid,
                ':connection' => $this->connection,
                ':queue'      => $job['queue'],
                ':payload'    => $job['payload'],
                ':exception'  => $exception,
            ]);
        } catch (\Throwable $e2) {
            $this->error("Failed to insert into DLQ: " . $e2->getMessage());
        }
    }

    /**
     * Log job failure details.
     */
    private function logJobFailure(array $job, \Throwable $e): void
    {
        $logPath = Config::get('app.log_path', __DIR__ . '/../../storage/logs');
        $logFile = $logPath . '/queue_failures.log';
        $entry = sprintf(
            "[%s] [job:%d] [%s] %s\n  Trace: %s\n\n",
            date('Y-m-d H:i:s'),
            $job['id'],
            $job['job_class'],
            $e->getMessage(),
            $e->getTraceAsString()
        );
        file_put_contents($logFile, $entry, FILE_APPEND);
    }

    /**
     * Signal handler for graceful shutdown.
     */
    public function onSignal(int $signal): void
    {
        $this->info("Received signal {$signal}. Finishing current job then stopping...");
        $this->shouldStop = true;
    }

    /**
     * Release a stuck job (was reserved but never completed/failed).
     */
    private function releaseStuckJob(int $jobId): void
    {
        try {
            $this->db->prepare(
                "UPDATE {$this->table} SET status = 'pending', reserved_at = NULL, updated_at = NOW()"
                . " WHERE id = :id AND status = 'reserved'"
            )->execute([':id' => $jobId]);
            $this->warning("Released stuck job ID {$jobId} back to pending.");
        } catch (\Throwable $e) {
            $this->error("Failed to release stuck job {$jobId}: " . $e->getMessage());
        }
    }

    /**
     * Release any jobs that have been reserved longer than the retry_after window.
     */
    private function releaseStaleJobs(): void
    {
        try {
            $cutoff = time() - $this->retryAfter;
            $stmt = $this->db->prepare(
                "UPDATE {$this->table} SET status = 'pending', reserved_at = NULL, updated_at = NOW()"
                . " WHERE status = 'reserved' AND reserved_at < :cutoff"
            );
            $stmt->execute([':cutoff' => $cutoff]);
            $released = $stmt->rowCount();
            if ($released > 0) {
                $this->info("Released {$released} stale reserved job(s) back to pending.");
            }
        } catch (\Throwable $e) {
            $this->error("Failed to release stale jobs: " . $e->getMessage());
        }
    }

    private function getReservedCount(): int
    {
        try {
            $stmt = $this->db->prepare("SELECT COUNT(*) FROM {$this->table} WHERE status = 'reserved'");
            $stmt->execute();
            return (int)$stmt->fetchColumn();
        } catch (\Throwable) {
            return 0;
        }
    }

    private function getFailedCount(): int
    {
        try {
            $stmt = $this->db->query("SELECT COUNT(*) FROM queue_failed_jobs");
            return (int)$stmt->fetchColumn();
        } catch (\Throwable) {
            return 0;
        }
    }
}
