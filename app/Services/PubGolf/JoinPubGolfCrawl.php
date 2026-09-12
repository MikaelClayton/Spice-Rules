<?php

namespace App\Services\PubGolf;

use App\Models\PubGolfCrawl;
use App\Models\PubGolfParticipant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class JoinPubGolfCrawl
{
    public function __construct(private RejoinPubGolfCrawl $rejoinPubGolfCrawl) {}

    public function handle(User $user, string $code): PubGolfCrawl
    {
        $crawl = PubGolfCrawl::query()
            ->where('join_code', $code)
            ->first();

        if ($crawl === null) {
            throw ValidationException::withMessages([
                'code' => 'No crawl uses that code.',
            ]);
        }

        if (! $crawl->isOpen()) {
            throw ValidationException::withMessages([
                'code' => 'That crawl has already wrapped up.',
            ]);
        }

        $existing = $crawl->participantFor($user);

        if ($existing !== null && $existing->isActive()) {
            return $crawl;
        }

        if ($existing !== null) {
            $this->rejoinPubGolfCrawl->handle($crawl, $user, 'code');

            return $crawl;
        }

        $current = PubGolfCrawl::currentFor($user);

        if ($current !== null) {
            throw ValidationException::withMessages([
                'code' => 'You are already on a crawl. Call it there before joining another.',
            ]);
        }

        DB::transaction(function () use ($crawl, $user): void {
            PubGolfParticipant::query()->create([
                'pub_golf_crawl_id' => $crawl->id,
                'user_id' => $user->id,
                'joined_at' => now(),
            ]);
        });

        return $crawl;
    }
}
