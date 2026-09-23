<?php
declare(strict_types=1);

namespace App\CLI;

use App\Core\Queue;

/**
 * ProcessLoyalty — CLI command to run loyalty points automation.
 *
 * Usage:
 *   php cli.php loyalty:process          (dispatch to queue)
 *   php cli.php loyalty:process --once   (run synchronously for cron)
 *
 * For cron (daily):
 *   0 6 * * * cd /path/to && php cli.php loyalty:process --once >> storage/logs/loyalty.log 2>&1
 */
class ProcessLoyalty extends Command
{
    protected string $name = 'loyalty:process';
    protected string $description = 'Process loyalty automation (expire points, birthday bonuses, tier recalc)';

    public function handle(array $args): void
    {
        $runNow = in_array('--once', $args, true);

        if ($runNow) {
            $this->info('Running loyalty processing synchronously...');

            $job = new \App\Jobs\ProcessLoyaltyJob();
            $job->handle();

            $expired = $job->getExpiredCount();
            $birthday = $job->getBirthdayCount();
            $tierChanges = $job->getTierDowngradeCount();
            $errors = $job->getErrors();

            $this->info("Points expired: {$expired}");
            $this->info("Birthday bonuses: {$birthday}");
            $this->info("Tier changes: {$tierChanges}");

            if ($expired + $birthday + $tierChanges > 0) {
                $this->success("Loyalty processing complete.");
            } else {
                $this->info('No actions needed.');
            }

            if (!empty($errors)) {
                foreach ($errors as $error) {
                    $this->error($error);
                }
            }
        } else {
            $this->info('Dispatching loyalty processing job to the queue...');

            Queue::dispatch(\App\Jobs\ProcessLoyaltyJob::class, []);

            $this->success('Job dispatched. Run `php cli.php queue:work --once` to process it.');
        }
    }
}
