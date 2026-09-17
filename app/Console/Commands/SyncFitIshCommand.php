<?php

namespace App\Console\Commands;

use App\Models\CronRun;
use App\Services\Cron\RecordCronRun;
use App\Services\FitIsh\SyncFitIshUsers;
use Illuminate\Console\Command;

class SyncFitIshCommand extends Command
{
    protected $signature = 'fit-ish:sync {--force : Refresh sessions that are already saved}';

    protected $description = 'Pull Lionheart sessions and profile summaries for Fit-Ish users';

    public function handle(SyncFitIshUsers $sync, RecordCronRun $recordCronRun): int
    {
        $force = (bool) $this->option('force');

        $result = $recordCronRun->handle(
            'fit-ish:sync',
            fn (CronRun $run): array => $sync->handle($run, $force),
        );

        $this->info("Synced {$result['synced']} Fit-Ish profile(s).");

        if ($result['sessions'] > 0) {
            $this->info("Saved {$result['sessions']} session(s).");
        }

        if ($result['skipped'] > 0) {
            $this->info("Skipped {$result['skipped']} profile(s) after a Lionheart error.");
        }

        return self::SUCCESS;
    }
}
