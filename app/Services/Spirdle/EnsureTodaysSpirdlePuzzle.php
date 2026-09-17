<?php

namespace App\Services\Spirdle;

use App\Models\SpirdlePuzzle;
use App\Models\SpirdleWord;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class EnsureTodaysSpirdlePuzzle
{
    public function handle(?Carbon $day = null): ?SpirdlePuzzle
    {
        $date = ($day ?? today())->toDateString();
        $existing = SpirdlePuzzle::query()
            ->with('word')
            ->whereDate('play_date', $date)
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        $word = $this->pickWord();

        if ($word === null) {
            return null;
        }

        try {
            return DB::transaction(function () use ($date, $word): SpirdlePuzzle {
                $puzzle = SpirdlePuzzle::query()->create([
                    'spirdle_word_id' => $word->id,
                    'play_date' => $date,
                ]);
                $puzzle->setRelation('word', $word);

                return $puzzle;
            });
        } catch (QueryException $exception) {
            $existing = SpirdlePuzzle::query()
                ->with('word')
                ->whereDate('play_date', $date)
                ->first();

            if ($existing !== null) {
                return $existing;
            }

            throw $exception;
        }
    }

    private function pickWord(): ?SpirdleWord
    {
        $usedIds = SpirdlePuzzle::query()->pluck('spirdle_word_id');

        $unused = SpirdleWord::query()
            ->answers()
            ->when($usedIds->isNotEmpty(), fn ($query) => $query->whereNotIn('id', $usedIds))
            ->inRandomOrder()
            ->first();

        if ($unused instanceof SpirdleWord) {
            return $unused;
        }

        return SpirdleWord::query()->answers()->inRandomOrder()->first();
    }
}
