<?php

namespace App\Services\Spirdle;

use App\Models\SpirdlePlay;
use Illuminate\Support\Collection;

class RankSpirdlePlays
{
    /**
     * @param  Collection<int, SpirdlePlay>  $plays
     * @return Collection<int, SpirdlePlay>
     */
    public function sorted(Collection $plays): Collection
    {
        return $plays
            ->sort(function (SpirdlePlay $left, SpirdlePlay $right): int {
                return ((int) $right->won) <=> ((int) $left->won)
                    ?: $left->guess_count <=> $right->guess_count
                    ?: ($left->duration_ms ?? PHP_INT_MAX) <=> ($right->duration_ms ?? PHP_INT_MAX)
                    ?: $left->invalid_word_count <=> $right->invalid_word_count
                    ?: ($left->finished_at?->timestamp ?? PHP_INT_MAX) <=> ($right->finished_at?->timestamp ?? PHP_INT_MAX)
                    ?: $left->id <=> $right->id;
            })
            ->values();
    }

    /**
     * @param  Collection<int, SpirdlePlay>  $plays
     * @return array<int, int>
     */
    public function ranks(Collection $plays): array
    {
        $ranks = [];
        $place = 1;
        $seen = 0;
        $previousKey = null;

        foreach ($this->sorted($plays) as $play) {
            $seen++;
            $key = implode(':', [
                (int) $play->won,
                $play->guess_count,
                $play->duration_ms ?? 'x',
                $play->invalid_word_count,
            ]);

            if ($previousKey !== $key) {
                $place = $seen;
            }

            $ranks[(int) $play->id] = $place;
            $previousKey = $key;
        }

        return $ranks;
    }

    public function placeFor(SpirdlePlay $play): ?int
    {
        $plays = SpirdlePlay::query()
            ->where('spirdle_puzzle_id', $play->spirdle_puzzle_id)
            ->finished()
            ->get();

        return $this->ranks($plays)[(int) $play->id] ?? null;
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
