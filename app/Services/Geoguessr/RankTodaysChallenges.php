<?php

namespace App\Services\Geoguessr;

use App\Models\GeoguesserChallenge;
use Illuminate\Support\Collection;

class RankTodaysChallenges
{
    /**
     * @param  Collection<int, GeoguesserChallenge>  $results
     * @return array<int, int>
     */
    public function ranks(Collection $results): array
    {
        $ranks = [];
        $place = 1;
        $seen = 0;
        $previousScore = null;

        foreach ($results as $result) {
            $seen++;
            $score = $result->total_score === null ? null : (int) $result->total_score;

            if ($previousScore !== $score) {
                $place = $seen;
            }

            $ranks[(int) $result->id] = $place;
            $previousScore = $score;
        }

        return $ranks;
    }

    public function placeFor(GeoguesserChallenge $challenge): ?int
    {
        $date = $challenge->attempted_at?->toDateString();

        if ($date === null) {
            return null;
        }

        $results = GeoguesserChallenge::query()
            ->whereDate('attempted_at', $date)
            ->orderByDesc('total_score')
            ->orderBy('updated_at')
            ->get();

        return $this->ranks($results)[(int) $challenge->id] ?? null;
    }

    public function ordinal(int $place): string
    {
        $modHundred = $place % 100;

        if ($modHundred >= 11 && $modHundred <= 13) {
            return $place.'th';
        }

        return $place.match ($place % 10) {
            1 => 'st',
            2 => 'nd',
            3 => 'rd',
            default => 'th',
        };
    }
}
