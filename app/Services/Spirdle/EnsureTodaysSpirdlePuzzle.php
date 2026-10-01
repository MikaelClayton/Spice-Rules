<?php

namespace App\Services\Spirdle;

use App\Models\SpirdlePractice;
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
            return $this->replaceDemotedAnswer($existing);
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
                return $this->replaceDemotedAnswer($existing);
            }

            throw $exception;
        }
    }

    private function replaceDemotedAnswer(SpirdlePuzzle $puzzle): SpirdlePuzzle
    {
        $puzzle->loadMissing('word');

        if ($puzzle->word?->is_answer !== false) {
            return $puzzle;
        }

        $hasFinishedPlays = $puzzle->plays()->finished()->exists();

        if ($hasFinishedPlays) {
            return $puzzle;
        }

        $replacement = $this->pickWord(excludeIds: [(int) $puzzle->spirdle_word_id]);

        if ($replacement === null) {
            return $puzzle;
        }

        $puzzle->spirdle_word_id = $replacement->id;
        $puzzle->save();
        $puzzle->setRelation('word', $replacement);

        return $puzzle;
    }

    /**
     * @param  list<int>  $excludeIds
     */
    private function pickWord(array $excludeIds = []): ?SpirdleWord
    {
        $usedIds = SpirdlePuzzle::query()->pluck('spirdle_word_id')
            ->merge(
                SpirdlePractice::query()
                    ->whereNull('finished_at')
                    ->pluck('spirdle_word_id'),
            )
            ->merge($excludeIds)
            ->unique()
            ->filter()
            ->values();

        $unused = SpirdleWord::query()
            ->answers()
            ->when($usedIds->isNotEmpty(), fn ($query) => $query->whereNotIn('id', $usedIds))
            ->inRandomOrder()
            ->first();

        if ($unused instanceof SpirdleWord) {
            return $unused;
        }

        return SpirdleWord::query()
            ->answers()
            ->when($excludeIds !== [], fn ($query) => $query->whereNotIn('id', $excludeIds))
            ->inRandomOrder()
            ->first();
    }
}
