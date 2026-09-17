<?php

namespace App\Services\Spirdle;

use App\Models\SpirdlePlay;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ControlSpirdlePlayTimer
{
    public function pause(User $user): ?SpirdlePlay
    {
        return $this->mutate($user, function (SpirdlePlay $play): void {
            if ($play->isFinished() || $play->running_since === null) {
                return;
            }

            $play->accumulated_ms = $play->elapsedMilliseconds();
            $play->running_since = null;
        });
    }

    public function resume(User $user): ?SpirdlePlay
    {
        return $this->mutate($user, function (SpirdlePlay $play): void {
            if ($play->isFinished() || $play->running_since !== null) {
                return;
            }

            $play->running_since = now();
        });
    }

    /**
     * @param  callable(SpirdlePlay): void  $callback
     */
    private function mutate(User $user, callable $callback): ?SpirdlePlay
    {
        return DB::transaction(function () use ($user, $callback): ?SpirdlePlay {
            $play = $this->todaysPlay($user);

            if ($play === null) {
                return null;
            }

            $callback($play);
            $play->save();

            return $play;
        });
    }

    private function todaysPlay(User $user): ?SpirdlePlay
    {
        return SpirdlePlay::query()
            ->with('puzzle.word')
            ->whereBelongsTo($user)
            ->whereHas('puzzle', fn ($query) => $query->whereDate('play_date', today()))
            ->lockForUpdate()
            ->first();
    }
}
