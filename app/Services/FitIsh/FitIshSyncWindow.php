<?php

namespace App\Services\FitIsh;

use App\Models\CronRun;
use App\Models\User;
use Illuminate\Support\Carbon;

class FitIshSyncWindow
{
    public function shouldSkip(): bool
    {
        return ! $this->hasOpened() || $this->alreadyFetchedToday();
    }

    public function skipMessage(): string
    {
        if (! $this->hasOpened()) {
            return 'Fit-Ish sync waits until '.$this->opensAt()->format('H:i').' SAST.';
        }

        return "Today's Fit-Ish data is already saved.";
    }

    public function hasOpened(): bool
    {
        return $this->now()->gte($this->opensAt());
    }

    public function alreadyFetchedToday(): bool
    {
        return CronRun::query()
            ->where('command', 'fit-ish:sync')
            ->where('status', 'success')
            ->where('started_at', '>=', $this->opensAt())
            ->where('started_at', '<=', $this->now()->copy()->endOfDay())
            ->where('profiles_synced', '>=', $this->fitIshUserCount())
            ->exists();
    }

    public function opensAt(): Carbon
    {
        return $this->now()->copy()->startOfDay()->setTimeFromTimeString(
            (string) config('fit-ish.sync_after', '07:00'),
        );
    }

    public function now(): Carbon
    {
        return now($this->timezone());
    }

    public function timezone(): string
    {
        return (string) config('fit-ish.timezone', 'Africa/Johannesburg');
    }

    private function fitIshUserCount(): int
    {
        return User::query()
            ->whereNotNull('fit_ish_user_id')
            ->where('fit_ish_user_id', '!=', '')
            ->count();
    }
}
