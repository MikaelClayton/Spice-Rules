<?php

namespace App\Services\Spirdle;

use App\Models\SpirdlePractice;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ControlSpirdlePracticeTimer
{
    public function pause(User $user): ?SpirdlePractice
    {
        return $this->mutate($user, function (SpirdlePractice $practice): void {
            if ($practice->isFinished() || $practice->running_since === null) {
                return;
            }

            $practice->accumulated_ms = $practice->elapsedMilliseconds();
            $practice->running_since = null;
        });
    }

    public function resume(User $user): ?SpirdlePractice
    {
        return $this->mutate($user, function (SpirdlePractice $practice): void {
            if ($practice->isFinished() || $practice->running_since !== null) {
                return;
            }

            $practice->running_since = now();
        });
    }

    /**
     * @param  callable(SpirdlePractice): void  $callback
     */
    private function mutate(User $user, callable $callback): ?SpirdlePractice
    {
        return DB::transaction(function () use ($user, $callback): ?SpirdlePractice {
            $practice = SpirdlePractice::query()
                ->with('word')
                ->whereBelongsTo($user)
                ->whereNull('finished_at')
                ->lockForUpdate()
                ->first();

            if ($practice === null) {
                return null;
            }

            $callback($practice);
            $practice->save();

            return $practice;
        });
    }
}
