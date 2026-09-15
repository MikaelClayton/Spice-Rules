<?php

namespace App\Services\PubGolf;

use App\Models\PubGolfCrawl;
use App\Models\PubGolfDrinkLog;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class LogPubGolfDrink
{
    public function __construct(private readonly ResolvePubGolfPlace $resolvePubGolfPlace) {}

    public function handle(
        PubGolfCrawl $crawl,
        User $user,
        PubGolfListedDrink $drink,
        ?float $latitude = null,
        ?float $longitude = null,
    ): PubGolfDrinkLog {
        if (! $crawl->isOpen() || ! $crawl->isActiveParticipant($user)) {
            throw ValidationException::withMessages([
                'drink' => 'You already called it on this crawl.',
            ]);
        }

        if ($drink->customId === null) {
            throw ValidationException::withMessages([
                'drink' => 'Pick a drink from the list.',
            ]);
        }

        if (! $user->allowsPubGolfLocation()) {
            $latitude = null;
            $longitude = null;
        }

        return PubGolfDrinkLog::query()->create([
            'pub_golf_crawl_id' => $crawl->id,
            'user_id' => $user->id,
            'drink_id' => $drink->customId,
            'location' => $this->resolvePubGolfPlace->handle($latitude, $longitude),
            'latitude' => $latitude,
            'longitude' => $longitude,
        ]);
    }
}
