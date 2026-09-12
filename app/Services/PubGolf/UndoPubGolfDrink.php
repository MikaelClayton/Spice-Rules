<?php

namespace App\Services\PubGolf;

use App\Models\PubGolfCrawl;
use App\Models\PubGolfDrinkLog;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class UndoPubGolfDrink
{
    public function handle(PubGolfCrawl $crawl, User $user): PubGolfDrinkLog
    {
        if (! $crawl->isOpen() || ! $crawl->isActiveParticipant($user)) {
            throw ValidationException::withMessages([
                'drink' => 'You already called it on this crawl.',
            ]);
        }

        $log = PubGolfDrinkLog::query()
            ->with('drink')
            ->whereBelongsTo($crawl, 'crawl')
            ->whereBelongsTo($user)
            ->orderByDesc('id')
            ->first();

        if ($log === null) {
            throw ValidationException::withMessages([
                'drink' => 'You have not logged a drink to undo.',
            ]);
        }

        $log->delete();

        return $log;
    }
}
