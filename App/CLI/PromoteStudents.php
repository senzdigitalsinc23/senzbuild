<?php
declare(strict_types=1);

namespace App\CLI;

use App\Services\StudentPromotionService;
use App\Repositories\StudentPromotionRepository;

class PromoteStudents extends Command
{
    private StudentPromotionService $promotionService;

    public function __construct()
    {
        parent::__construct();
        $promotionRepo = new StudentPromotionRepository();
        $subjectRepo = new \App\Repositories\SubjectRepository();
        $scoreRepo = new \App\Repositories\StudentScoreRepository();
        $this->promotionService = new StudentPromotionService($promotionRepo, $subjectRepo, $scoreRepo);
    }

    public function handle(array $args): void
    {
        $promotionType = $args[0] ?? null;
        
        if (!$promotionType || !in_array($promotionType, ['normal', 'special', 'graduate'])) {
            $this->error("Usage: php bin/console promote:students <normal|special|graduate> [options]");
            $this->info("Examples:");
            $this->info("  php bin/console promote:students normal");
            $this->info("  php bin/console promote:students special --from=1 --to=2");
            $this->info("  php bin/console promote:students graduate");
            return;
        }

        if ($promotionType === 'normal') {
            $this->promoteAllNormal($args);
        } elseif ($promotionType === 'special') {
            $this->promoteSpecial($args);
        } elseif ($promotionType === 'graduate') {
            $this->graduateAll($args);
        }
    }

    private function parseOptions(array $args): array
    {
        $opts = [];
        foreach ($args as $a) {
            if (str_starts_with($a, '--')) {
                $parts = explode('=', $a, 2);
                $key = ltrim($parts[0], '-');
                $value = $parts[1] ?? true;
                $opts[$key] = $value;
            }
        }
        return $opts;
    }

    private function promoteAllNormal(array $args): void
    {
        $this->info("Promoting students to next class (bulk)...");
        $opts = $this->parseOptions($args);

        // Expect --students=1,2,3 or --file=path and --current=<class_id>
        $students = [];
        if (!empty($opts['students'])) {
            $students = array_filter(array_map('trim', explode(',', $opts['students'])));
        } elseif (!empty($opts['file'])) {
            $path = $opts['file'];
            if (!file_exists($path)) {
                $this->error("File not found: {$path}");
                return;
            }
            $content = file_get_contents($path);
            $decoded = json_decode($content, true);
            if (is_array($decoded)) {
                $students = $decoded;
            } else {
                // try CSV
                $lines = array_filter(array_map('trim', explode("\n", $content)));
                $students = [];
                foreach ($lines as $ln) {
                    $students = array_merge($students, array_map('trim', explode(',', $ln)));
                }
            }
        }

        $currentClass = $opts['current'] ?? $opts['from'] ?? null;
        if (empty($students)) {
            $this->error('Provide --students="id1,id2" or --file=path');
            return;
        }

        // currentClass is optional; service will resolve per-student if omitted
        $result = $this->promotionService->bulkPromoteNormal($students, $currentClass ?? null, 'cli', $opts['remarks'] ?? null);
        $this->info("Result: " . json_encode($result));
    }

    private function promoteSpecial(array $args): void
    {
        $this->info("Moving students to special class (bulk)...");

        $opts = $this->parseOptions($args);
        $students = [];
        if (!empty($opts['students'])) {
            $students = array_filter(array_map('trim', explode(',', $opts['students'])));
        } elseif (!empty($opts['file'])) {
            $path = $opts['file'];
            if (!file_exists($path)) {
                $this->error("File not found: {$path}");
                return;
            }
            $content = file_get_contents($path);
            $decoded = json_decode($content, true);
            if (is_array($decoded)) {
                $students = $decoded;
            } else {
                $lines = array_filter(array_map('trim', explode("\n", $content)));
                $students = [];
                foreach ($lines as $ln) {
                    $students = array_merge($students, array_map('trim', explode(',', $ln)));
                }
            }
        }

        $target = $opts['to'] ?? null;
        if (empty($students) || !$target) {
            $this->error('Provide --students="id1,id2" and --to=<target_class_id>');
            return;
        }

        $result = $this->promotionService->bulkPromoteSpecial($students, (int)$target, 'cli', $opts['remarks'] ?? null);
        $this->info("Result: " . json_encode($result));
    }

    private function graduateAll(array $args): void
    {
        $this->info("Graduating students (bulk)...");
        $opts = $this->parseOptions($args);
        $students = [];
        if (!empty($opts['students'])) {
            $students = array_filter(array_map('trim', explode(',', $opts['students'])));
        } elseif (!empty($opts['file'])) {
            $path = $opts['file'];
            if (!file_exists($path)) {
                $this->error("File not found: {$path}");
                return;
            }
            $content = file_get_contents($path);
            $decoded = json_decode($content, true);
            if (is_array($decoded)) {
                $students = $decoded;
            } else {
                $lines = array_filter(array_map('trim', explode("\n", $content)));
                $students = [];
                foreach ($lines as $ln) {
                    $students = array_merge($students, array_map('trim', explode(',', $ln)));
                }
            }
        }

        if (empty($students)) {
            $this->error('Provide --students="id1,id2" or --file=path');
            return;
        }

        $result = $this->promotionService->bulkGraduate($students, 'cli', $opts['remarks'] ?? null);
        $this->info("Result: " . json_encode($result));
    }
}
