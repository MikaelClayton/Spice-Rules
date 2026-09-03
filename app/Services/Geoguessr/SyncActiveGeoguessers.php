<?php

namespace App\Services\Geoguessr;

use App\Models\Geoguesser;
use Carbon\Carbon;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Log;

class SyncActiveGeoguessers
{
    public function __construct(private readonly GeoguessrClient $client) {}

    public function handle(): int
    {
        $synced = 0;

        $geoguessers = Geoguesser::query()
            ->where('is_active', true)
            ->whereNotNull('ncfa')
            ->where('ncfa', '!=', '')
            ->get();

        foreach ($geoguessers as $geoguesser) {
            try {
                $this->sync($geoguesser);
                $synced++;
            } catch (RequestException|ConnectionException $exception) {
                Log::warning('GeoGuessr sync failed', [
                    'geoguesser_id' => $geoguesser->id,
                    'status' => $exception instanceof RequestException ? $exception->response?->status() : null,
                ]);
            }
        }

        return $synced;
    }

    public function sync(Geoguesser $geoguesser): void
    {
        $ncfa = (string) $geoguesser->ncfa;

        $this->syncProfile($geoguesser, $this->client->profile($ncfa));
        $this->syncWeek($geoguesser, $this->client->weeklyDailyChallenges($ncfa));
        $this->syncStats($geoguesser, $this->client->stats($ncfa));
    }

    /**
     * @param  array<string, mixed>  $profile
     */
    private function syncProfile(Geoguesser $geoguesser, array $profile): void
    {
        $geoguesser->applyFromProfile($profile);
        $geoguesser->save();
    }

    /**
     * @param  list<array<string, mixed>>  $days
     */
    private function syncWeek(Geoguesser $geoguesser, array $days): void
    {
        foreach ($days as $day) {
            $token = $day['challengeToken'] ?? null;
            $result = is_array($day['gameResult'] ?? null) ? $day['gameResult'] : null;

            if (! is_string($token) || $token === '' || $result === null) {
                continue;
            }

            $geoguesser->challenges()->updateOrCreate(
                ['challenge_token' => $token],
                [
                    'attempted_at' => $this->dateForDayOfWeek((int) ($day['dayOfWeek'] ?? 0)),
                    'total_score' => $result['totalScore'] ?? null,
                    'geoguesser_guid' => $result['id'] ?? null,
                    'total_distance' => isset($result['totalDistance']) ? (int) round((float) $result['totalDistance']) : null,
                    'total_steps_count' => $result['totalStepsCount'] ?? null,
                ],
            );
        }
    }

    /**
     * @param  array<string, mixed>  $stats
     */
    private function syncStats(Geoguesser $geoguesser, array $stats): void
    {
        $geoguesser->update([
            'daily_challenge_streak' => $stats['dailyChallengeStreak'] ?? $geoguesser->daily_challenge_streak,
            'daily_challenge_current_streak' => $stats['dailyChallengeCurrentStreak'] ?? $geoguesser->daily_challenge_current_streak,
        ]);

        $recent = $stats['dailyChallengesRolling7Days'] ?? [];

        if (! is_array($recent)) {
            return;
        }

        foreach ($recent as $entry) {
            $token = $entry['challengeToken'] ?? null;

            if (! is_string($token) || $token === '') {
                continue;
            }

            $challenge = $geoguesser->challenges()->where('challenge_token', $token)->first();

            if ($challenge === null) {
                continue;
            }

            $challenge->update([
                'attempted_at' => isset($entry['date']) ? Carbon::parse($entry['date']) : $challenge->attempted_at,
                'total_score' => $entry['totalScore'] ?? $challenge->total_score,
                'total_distance' => isset($entry['totalDistance'])
                    ? (int) round((float) $entry['totalDistance'])
                    : $challenge->total_distance,
            ]);
        }
    }

    private function dateForDayOfWeek(int $dayOfWeek): Carbon
    {
        return now()->startOfWeek(Carbon::SUNDAY)->addDays($dayOfWeek);
    }
}
