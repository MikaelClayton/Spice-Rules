<?php

namespace App\Services\Spirdle;

use App\Models\SpirdlePlay;
use App\Models\SpirdlePuzzle;
use App\Models\User;

class StartSpirdlePlay
{
    public function __construct(private readonly ControlSpirdlePlayTimer $timer) {}

    public function handle(User $user, SpirdlePuzzle $puzzle): SpirdlePlay
    {
        $play = SpirdlePlay::query()->firstOrCreate(
            [
                'user_id' => $user->id,
                'spirdle_puzzle_id' => $puzzle->id,
            ],
            [
                'guesses' => [],
                'guess_count' => 0,
                'invalid_word_count' => 0,
                'accumulated_ms' => 0,
                'won' => false,
                'started_at' => now(),
                'running_since' => now(),
            ],
        );

        if (! $play->wasRecentlyCreated && ! $play->isFinished()) {
            $resumed = $this->timer->resume($user);
            $play = $resumed ?? $play->fresh() ?? $play;
        }

        if (! $play->relationLoaded('puzzle')) {
            $play->setRelation('puzzle', $puzzle);
        }

        return $play;
    }
}
