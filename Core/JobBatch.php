<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Job Batch — group multiple jobs and track their collective progress.
 *
 * Usage:
 *   $batch = new JobBatch('import-users', [
 *       new ImportUsersJob($chunk1),
 *       new ImportUsersJob($chunk2),
 *       new ImportUsersJob($chunk3),
 *   ]);
 *
 *   $batch->dispatch();
 *   $batch->refresh();           // re-query job statuses
 *   $batch->isComplete();        // true when all jobs done/failed
 *   $batch->progress();          // 66 (2 of 3 done)
 *   $batch->getResults();        // ['success' => [...], 'failed' => [...]]
 */
class JobBatch
{
    protected string $id;
    protected string $name;
    /** @var array<int, array{class: string, data: array, status: string}> */
    protected array $jobs = [];
    protected int $dispatchedAt;
    protected ?int $completedAt = null;

    /**
     * @param string $name Batch name
     * @param array<int, array{class: string, data: array}> $jobs List of [class, data] pairs
     */
    public function __construct(string $name, array $jobs)
    {
        $this->id = bin2hex(random_bytes(16));
        $this->name = $name;
        $this->jobs = array_map(fn($j) => [
            'class' => $j['class'],
            'data'  => $j['data'] ?? [],
            'status' => 'pending',
        ], $jobs);
        $this->dispatchedAt = time();
    }

    /**
     * Dispatch all jobs in the batch.
     */
    public function dispatch(Queue $queue = null): void
    {
        foreach ($this->jobs as $i => $job) {
            $q = $queue ?? new Queue(app()->make(\App\Core\Logger::class));
            $jobId = $q->dispatch($job['class'], $job['data']);
            $this->jobs[$i]['queue_id'] = $jobId;
            $this->jobs[$i]['status'] = 'dispatched';
        }
    }

    /**
     * Mark a job as completed.
     */
    public function complete(int $index): void
    {
        if (isset($this->jobs[$index])) {
            $this->jobs[$index]['status'] = 'completed';
            $this->checkCompletion();
        }
    }

    /**
     * Mark a job as failed.
     */
    public function fail(int $index, string $error = ''): void
    {
        if (isset($this->jobs[$index])) {
            $this->jobs[$index]['status'] = 'failed';
            $this->jobs[$index]['error'] = $error;
            $this->checkCompletion();
        }
    }

    /**
     * Check if all jobs are done.
     */
    public function isComplete(): bool
    {
        return $this->completedAt !== null;
    }

    /**
     * Get batch progress (0-100).
     */
    public function progress(): int
    {
        if ($this->isComplete() || empty($this->jobs)) {
            return $this->isComplete() ? 100 : 0;
        }
        $done = count(array_filter($this->jobs, fn($j) => in_array($j['status'], ['completed', 'failed'])));
        return (int)(($done / count($this->jobs)) * 100);
    }

    /**
     * Get batch results summary.
     */
    public function getResults(): array
    {
        return [
            'total'    => count($this->jobs),
            'completed'=> count(array_filter($this->jobs, fn($j) => $j['status'] === 'completed')),
            'failed'   => count(array_filter($this->jobs, fn($j) => $j['status'] === 'failed')),
            'pending'  => count(array_filter($this->jobs, fn($j) => $j['status'] === 'pending')),
            'jobs'     => $this->jobs,
        ];
    }

    /**
     * Get batch ID.
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * Get batch name.
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Get the dispatched timestamp.
     */
    public function getDispatchedAt(): int
    {
        return $this->dispatchedAt;
    }

    /**
     * Internal: check if all jobs are resolved.
     */
    protected function checkCompletion(): void
    {
        $allDone = !empty($this->jobs) &&
            count(array_filter($this->jobs, fn($j) => !in_array($j['status'], ['pending', 'dispatched']))) === count($this->jobs);

        if ($allDone && $this->completedAt === null) {
            $this->completedAt = time();
        }
    }
}

/**
 * Batch Registry — tracks active batches.
 */
class BatchRegistry
{
    protected static array $batches = [];

    public static function store(JobBatch $batch): void
    {
        self::$batches[$batch->getId()] = $batch;
    }

    public static function get(string $id): ?JobBatch
    {
        return self::$batches[$id] ?? null;
    }

    public static function all(): array
    {
        return self::$batches;
    }

    public static function remove(string $id): void
    {
        unset(self::$batches[$id]);
    }

    public static function flush(): void
    {
        self::$batches = [];
    }
}
