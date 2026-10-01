<?php

namespace App\Services\Spirdle;

use App\Models\SpirdlePractice;
use App\Models\SpirdleWord;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class StartSpirdlePractice
{
    public function __construct(
        private readonly EnsureTodaysSpirdlePuzzle $ensureTodaysSpirdlePuzzle,
    ) {}

    public function handle(User $user): ?SpirdlePractice
    {
        return DB::transaction(function () use ($user): ?SpirdlePractice {
            $existing = SpirdlePractice::query()
                ->with('word')
                ->whereBelongsTo($user)
                ->whereNull('finished_at')
                ->lockForUpdate()
                ->first();

            if ($existing !== null && ($this->usesTodaysWord($existing) || ! $this->isEligibleAnswer($existing))) {
                $existing->delete();
                $existing = null;
            }

            if ($existing !== null) {
                if ($existing->isPaused()) {
                    $existing->running_since = now();
                    $existing->save();
                }

                return $existing;
            }

            $word = $this->pickWord($user);

            if ($word === null) {
                return null;
            }

            $practice = SpirdlePractice::query()->create([
                'user_id' => $user->id,
                'spirdle_word_id' => $word->id,
                'guesses' => [],
                'guess_count' => 0,
                'invalid_word_count' => 0,
                'accumulated_ms' => 0,
                'won' => false,
                'started_at' => now(),
                'running_since' => now(),
            ]);
            $practice->setRelation('word', $word);

            return $practice;
        });
    }

    private function usesTodaysWord(SpirdlePractice $practice): bool
    {
        $todayId = $this->todaysWordId();

        return $todayId !== null && (int) $practice->spirdle_word_id === $todayId;
    }

    private function isEligibleAnswer(SpirdlePractice $practice): bool
    {
        $practice->loadMissing('word');

        return $practice->word?->is_answer === true;
    }

    private function pickWord(User $user): ?SpirdleWord
    {
        $excludeIds = collect([$this->todaysWordId()])->filter()->values();
        $usedIds = SpirdlePractice::query()
            ->whereBelongsTo($user)
            ->pluck('spirdle_word_id');

        $fresh = SpirdleWord::query()
            ->answers()
            ->when($excludeIds->isNotEmpty(), fn ($query) => $query->whereNotIn('id', $excludeIds))
            ->when($usedIds->isNotEmpty(), fn ($query) => $query->whereNotIn('id', $usedIds))
            ->inRandomOrder()
            ->first();

        if ($fresh instanceof SpirdleWord) {
            return $fresh;
        }

        return SpirdleWord::query()
            ->answers()
            ->when($excludeIds->isNotEmpty(), fn ($query) => $query->whereNotIn('id', $excludeIds))
            ->inRandomOrder()
            ->first();
    }

    private function todaysWordId(): ?int
    {
        $id = $this->ensureTodaysSpirdlePuzzle->handle()?->spirdle_word_id;

        return $id === null ? null : (int) $id;
    }
}
