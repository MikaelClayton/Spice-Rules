<?php

namespace App\Console\Commands;

use App\Models\CronRun;
use App\Services\Geoguessr\SyncActiveGeoguessers;
use Illuminate\Console\Command;

class SyncGeoguessrCommand extends Command
{
    protected $signature = 'geoguessr:sync';

    protected $description = 'Pull GeoGuessr profiles, weekly dailies, and streaks for active players with an ncfa cookie';

    public function handle(SyncActiveGeoguessers $sync): int
    {
        $started = hrtime(true);
        $run = CronRun::query()->create([
            'command' => 'geoguessr:sync',
            'status' => 'running',
            'profiles_synced' => 0,
            'duration_ms' => 0,
            'started_at' => now(),
        ]);

        try {
            $synced = $sync->handle($run);
            $run->update([
                'status' => 'success',
                'profiles_synced' => $synced,
                'duration_ms' => (int) ((hrtime(true) - $started) / 1_000_000),
                'finished_at' => now(),
            ]);

            $this->info("Synced {$synced} GeoGuessr profile(s).");

            return self::SUCCESS;
        } catch (\Throwable $exception) {
            $run->update([
                'status' => 'failed',
                'duration_ms' => (int) ((hrtime(true) - $started) / 1_000_000),
                'error_message' => $exception->getMessage(),
                'finished_at' => now(),
            ]);

            throw $exception;
        }
    }
}
