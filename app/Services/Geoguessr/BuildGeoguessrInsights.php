<?php

namespace App\Services\Geoguessr;

use App\Models\GeoguesserChallenge;
use App\Models\GeoguesserRound;
use Illuminate\Support\Collection;

class BuildGeoguessrInsights
{
    /**
     * @param  Collection<int, GeoguesserChallenge>  $challenges
     * @return array{rounds: list<array<string, mixed>>}
     */
    public function payload(Collection $challenges, ?int $viewerId): array
    {
        $today = today()->toDateString();
        $hideTodayPlaces = $this->shouldHideTodayPlaces($challenges, $viewerId, $today);

        return [
            'rounds' => $challenges
                ->flatMap(fn (GeoguesserChallenge $challenge): Collection => $this->roundRows($challenge, $hideTodayPlaces, $today))
                ->values()
                ->all(),
        ];
    }

    /**
     * @param  Collection<int, GeoguesserChallenge>  $challenges
     */
    private function shouldHideTodayPlaces(Collection $challenges, ?int $viewerId, string $today): bool
    {
        if ($viewerId === null) {
            return true;
        }

        return ! $challenges->contains(function (GeoguesserChallenge $challenge) use ($viewerId, $today): bool {
            return $challenge->attempted_at?->toDateString() === $today
                && $challenge->geoguesser?->user_id === $viewerId
                && $challenge->rounds->isNotEmpty();
        });
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function roundRows(GeoguesserChallenge $challenge, bool $hideTodayPlaces, string $today): Collection
    {
        $date = $challenge->attempted_at?->toDateString();
        $hidePlace = $hideTodayPlaces && $date === $today;
        $playerId = $challenge->geoguesser_id;

        return $challenge->rounds->map(function (GeoguesserRound $round) use ($playerId, $date, $challenge, $hidePlace): array {
            $row = [
                'playerId' => $playerId,
                'date' => $date,
                'token' => $challenge->challenge_token,
                'round' => $round->round_number,
                'score' => $round->score,
            ];

            if ($hidePlace) {
                return $row;
            }

            $country = filled($round->country_code) ? strtoupper((string) $round->country_code) : null;
            $country = $country === 'UK' ? 'GB' : $country;

            return [
                ...$row,
                'country' => $country,
                'continent' => ContinentForCountry::name($country),
                'actualLat' => $round->actual_lat,
                'actualLng' => $round->actual_lng,
                'guessLat' => $round->guess_lat,
                'guessLng' => $round->guess_lng,
                'distance' => $round->distance_in_meters,
            ];
        });
    }
}
