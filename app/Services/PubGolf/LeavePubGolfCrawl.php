<?php

namespace App\Services\PubGolf;

use App\Models\PubGolfCrawl;
use App\Models\PubGolfParticipant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LeavePubGolfCrawl
{
    public function handle(PubGolfCrawl $crawl, User $user): PubGolfParticipant
    {
        return DB::transaction(function () use ($crawl, $user): PubGolfParticipant {
            $participant = PubGolfParticipant::query()
                ->whereBelongsTo($crawl, 'crawl')
                ->whereBelongsTo($user)
                ->lockForUpdate()
                ->first();

            if ($participant === null) {
                throw ValidationException::withMessages([
                    'crawl' => 'You are not on this crawl.',
                ]);
            }

            if ($participant->isActive()) {
                $leftAt = now();
                $participant->update(['left_at' => $leftAt]);
            }

            $stillActive = PubGolfParticipant::query()
                ->whereBelongsTo($crawl, 'crawl')
                ->active()
                ->lockForUpdate()
                ->exists();

            if (! $stillActive && $crawl->ended_at === null) {
                $crawl->update(['ended_at' => $participant->left_at ?? now()]);
            }

            return $participant->refresh();
        });
    }
}
