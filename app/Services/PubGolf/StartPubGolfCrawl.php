<?php

namespace App\Services\PubGolf;

use App\Models\PubGolfCrawl;
use App\Models\PubGolfParticipant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StartPubGolfCrawl
{
    public function __construct(private GeneratePubGolfJoinCode $generatePubGolfJoinCode) {}

    public function handle(User $user, string $name): PubGolfCrawl
    {
        if (PubGolfCrawl::currentFor($user) !== null) {
            throw ValidationException::withMessages([
                'name' => 'You are already on a crawl. Call it there before starting another.',
            ]);
        }

        return DB::transaction(function () use ($user, $name): PubGolfCrawl {
            $startedAt = now();

            $crawl = PubGolfCrawl::query()->create([
                'user_id' => $user->id,
                'name' => $name,
                'join_code' => $this->generatePubGolfJoinCode->handle(),
                'started_at' => $startedAt,
            ]);

            PubGolfParticipant::query()->create([
                'pub_golf_crawl_id' => $crawl->id,
                'user_id' => $user->id,
                'joined_at' => $startedAt,
            ]);

            return $crawl;
        });
    }
}
