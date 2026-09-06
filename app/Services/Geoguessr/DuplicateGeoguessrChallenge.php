<?php

namespace App\Services\Geoguessr;

use App\Models\Geoguesser;
use App\Models\GeoguesserChallenge;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class DuplicateGeoguessrChallenge
{
    public function handle(GeoguesserChallenge $source, Geoguesser $target): GeoguesserChallenge
    {
        $this->assertCanCopy($source, $target);
        $source->loadMissing('rounds');

        return DB::transaction(function () use ($source, $target): GeoguesserChallenge {
            $copy = $this->targetChallenge($source, $target);
            $this->writeCopy($source, $copy);

            return $copy->refresh()->load('rounds');
        });
    }

    public function copyOnto(GeoguesserChallenge $source, Geoguesser $target): GeoguesserChallenge
    {
        $this->assertCanCopy($source, $target);
        $source->loadMissing('rounds');

        $copy = new GeoguesserChallenge(['geoguesser_id' => $target->id]);
        $this->writeCopy($source, $copy);

        return $copy->refresh()->load('rounds');
    }

    private function assertCanCopy(GeoguesserChallenge $source, Geoguesser $target): void
    {
        if ($source->geoguesser_id === $target->id) {
            throw new InvalidArgumentException('Pick a different GeoGuessr.');
        }

        if (! $target->is_active) {
            throw new InvalidArgumentException('That GeoGuessr profile is not active.');
        }
    }

    private function writeCopy(GeoguesserChallenge $source, GeoguesserChallenge $copy): void
    {
        $copy->fill($source->only([
            'attempted_at',
            'challenge_token',
            'game_token',
            'map_name',
            'total_score',
            'geoguesser_guid',
            'total_distance',
            'total_steps_count',
        ]));
        $copy->save();

        $copy->rounds()->delete();

        foreach ($source->rounds as $round) {
            $copy->rounds()->create($round->only([
                'round_number',
                'actual_lat',
                'actual_lng',
                'guess_lat',
                'guess_lng',
                'score',
                'percentage',
                'time',
                'steps_count',
                'distance_in_meters',
                'timed_out',
                'timed_out_with_guess',
                'skipped_round',
                'heading',
                'pitch',
                'zoom',
                'pano_id',
                'country_code',
                'guess_country_code',
                'started_at',
            ]));
        }
    }

    private function targetChallenge(GeoguesserChallenge $source, Geoguesser $target): GeoguesserChallenge
    {
        $date = $source->attempted_at?->toDateString();

        $copy = null;

        if (filled($source->challenge_token)) {
            $copy = $target->challenges()
                ->where('challenge_token', $source->challenge_token)
                ->first();
        }

        $copy ??= $target->challenges()
            ->when($date, fn ($query) => $query->whereDate('attempted_at', $date))
            ->first();

        return $copy ?? new GeoguesserChallenge(['geoguesser_id' => $target->id]);
    }
}
