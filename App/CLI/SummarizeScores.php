<?php
declare(strict_types=1);

namespace App\CLI;

use Jobs\SummarizeScoresJob;
use App\Core\Session;

class SummarizeScores extends Command
{
    protected string $name = 'scores:summarize';
    protected string $description = 'Summarizes student scores into the summary report table.';

    public function handle(array $args): void
    {
        $academicYear = $args[0] ?? null;
        $term = $args[1] ?? null;

        if (!$academicYear || !$term) {
            // Default to current session if not provided, or ask user?
            // For CLI context, Session might not be populated same as web.
            // Let's require them for now or hardcode for testing.
             $this->error("Usage: php app scores:summarize <academic_year> <term>");
             return;
        }

        $this->info("Summarizing scores for Academic Year: {$academicYear}, Term: {$term}");

        $job = new SummarizeScoresJob();
        try {
            $job->handle($academicYear, $term);
            $this->success("Score summarization completed successfully.");
        } catch (\Exception $e) {
            $this->error("Error: " . $e->getMessage());
        }
    }
}
