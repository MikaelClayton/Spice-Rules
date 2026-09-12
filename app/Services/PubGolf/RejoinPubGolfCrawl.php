<?php

namespace App\Services\PubGolf;

use App\Models\PubGolfCrawl;
use App\Models\PubGolfParticipant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RejoinPubGolfCrawl
{
    public function handle(PubGolfCrawl $crawl, User $user, string $errorKey = 'crawl'): PubGolfParticipant
    {
        if (! $crawl->isOpen()) {
            throw ValidationException::withMessages([
                $errorKey => 'That crawl has already wrapped up.',
            ]);
        }

        $current = PubGolfCrawl::currentFor($user);

        if ($current !== null && $current->isNot($crawl)) {
            throw ValidationException::withMessages([
                $errorKey => 'You are already on a crawl. Call it there before joining another.',
            ]);
        }

        return DB::transaction(function () use ($crawl, $user, $errorKey): PubGolfParticipant {
            $participant = PubGolfParticipant::query()
                ->whereBelongsTo($crawl, 'crawl')
                ->whereBelongsTo($user)
                ->lockForUpdate()
                ->first();

            if ($participant === null) {
                throw ValidationException::withMessages([
                    $errorKey => 'You are not on this crawl.',
                ]);
            }

            if ($participant->isActive()) {
                return $participant;
            }

            $participant->update(['left_at' => null]);

            return $participant->refresh();
        });
    }
}
