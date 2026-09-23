<?php
declare(strict_types=1);

namespace App\CLI;

use App\Core\Queue;

/**
 * ExpireCredits — CLI command to dispatch the store credit expiration job.
 *
 * Usage:
 *   php cli.php credits:expire
 *   php cli.php credits:expire --once   (process one batch synchronously)
 *
 * For cron (daily at midnight):
 *   0 0 * * * cd /path/to && php cli.php credits:expire --once >> storage/logs/expire_credits.log 2>&1
 */
class ExpireCredits extends Command
{
    protected string $name = 'credits:expire';
    protected string $description = 'Dispatch store credit expiration job to the queue';

    public function handle(array $args): void
    {
        $runNow = in_array('--once', $args, true);

        if ($runNow) {
            $this->info('Running store credit expiration synchronously...');

            $job = new \App\Jobs\ExpireStoreCreditsJob();
            $job->handle();

            $count = $job->getExpiredCount();
            $errors = $job->getErrors();

            if ($count > 0) {
                $this->success("Expired {$count} store credit(s).");
            } else {
                $this->info('No expired credits found.');
            }

            if (!empty($errors)) {
                foreach ($errors as $error) {
                    $this->error($error);
                }
            }
        } else {
            $this->info('Dispatching store credit expiration job to the queue...');

            Queue::dispatch(\App\Jobs\ExpireStoreCreditsJob::class, []);

            $this->success('Job dispatched. Run `php cli.php queue:work --once` to process it.');
        }
    }
}
