<?php

namespace App\Console\Commands;

use App\Services\Geoguessr\SyncActiveGeoguessers;
use Illuminate\Console\Command;

class SyncGeoguessrCommand extends Command
{
    protected $signature = 'geoguessr:sync';

    protected $description = 'Pull GeoGuessr profiles, weekly dailies, and streaks for active players with an ncfa cookie';

    public function handle(SyncActiveGeoguessers $sync): int
    {
        $synced = $sync->handle();

        $this->info("Synced {$synced} GeoGuessr profile(s).");

        return self::SUCCESS;
    }
}
