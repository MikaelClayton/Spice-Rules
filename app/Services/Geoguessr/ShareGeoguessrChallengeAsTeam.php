<?php

namespace App\Services\Geoguessr;

use App\Models\Geoguesser;
use App\Models\GeoguesserChallenge;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ShareGeoguessrChallengeAsTeam
{
    public function __construct(private DuplicateGeoguessrChallenge $duplicate) {}

    /**
     * @param  Collection<int, Geoguesser>  $targets
     */
    public function handle(GeoguesserChallenge $source, Collection $targets): void
    {
        if ($targets->isEmpty()) {
            throw new InvalidArgumentException('Pick at least one GeoGuessr.');
        }

        DB::transaction(function () use ($source, $targets): void {
            foreach ($targets as $target) {
                $copy = $this->duplicate->copyOnto($source, $target);
                $copy->is_done_as_team = true;
                $copy->save();
            }

            $source->is_done_as_team = true;
            $source->save();
        });
    }
}
