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

        $log = PubGolfDrinkLog::query()->create([
            'pub_golf_crawl_id' => $crawl->id,
            'user_id' => $user->id,
            'drink_id' => $drink->customId,
            'location' => null,
            'latitude' => $latitude,
            'longitude' => $longitude,
        ]);

        if ($latitude !== null && $longitude !== null) {
            $this->resolvePlaceAfterResponse($log->id, $latitude, $longitude);
        }

        return $log;
    }

    private function resolvePlaceAfterResponse(int $logId, float $latitude, float $longitude): void
    {
        defer(static function (): void {
            if (function_exists('fastcgi_finish_request')) {
                fastcgi_finish_request();
            }
        }, 'pub-golf-flush-response');

        $resolvePubGolfPlace = $this->resolvePubGolfPlace;

        defer(static function () use ($logId, $latitude, $longitude, $resolvePubGolfPlace): void {
            $location = $resolvePubGolfPlace->handle($latitude, $longitude);

            if (! is_string($location) || $location === '') {
                return;
            }

            PubGolfDrinkLog::query()->whereKey($logId)->update(['location' => $location]);
        }, 'pub-golf-resolve-place-'.$logId);
    }
}
