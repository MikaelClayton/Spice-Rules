<?php

namespace App\Console\Commands;

use App\Models\CronRun;
use App\Services\Cron\RecordCronRun;
use App\Services\Geoguessr\SyncActiveGeoguessers;
use Illuminate\Console\Command;

class SyncGeoguessrCommand extends Command
{
    protected $signature = 'geoguessr:sync {--force : Sync even if today\'s challenge is already saved}';

    protected $description = 'Pull GeoGuessr profiles, weekly dailies, and streaks for active players with an ncfa cookie';

    public function handle(SyncActiveGeoguessers $sync, RecordCronRun $recordCronRun): int
    {
        $result = $recordCronRun->handle(
            'geoguessr:sync',
            fn (CronRun $run): array => $sync->handle($run, (bool) $this->option('force')),
        );

        $this->info("Synced {$result['synced']} GeoGuessr profile(s).");

        if ($result['skipped'] > 0) {
            $this->info("Skipped {$result['skipped']} already synced for today. Use --force to refresh.");
        }

        return self::SUCCESS;
    }
}
