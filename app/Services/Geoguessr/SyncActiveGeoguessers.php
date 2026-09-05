<?php

namespace App\Services\Geoguessr;

use App\Models\CronRun;
use App\Models\Geoguesser;
use App\Models\GeoguesserChallenge;
use Carbon\Carbon;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Log;

class SyncActiveGeoguessers
{
    public function __construct(private readonly GeoguessrClient $client) {}

    /**
     * @return array{synced: int, skipped: int}
     */
    public function handle(?CronRun $cronRun = null, bool $force = false): array
    {
        $synced = 0;
        $skipped = 0;

        $geoguessers = Geoguesser::query()
            ->where('is_active', true)
            ->whereNotNull('ncfa')
            ->where('ncfa', '!=', '')
            ->get();

        foreach ($geoguessers as $geoguesser) {
            if (! $force && $this->alreadySyncedToday($geoguesser)) {
                $skipped++;

                continue;
            }

            try {
                $this->sync($geoguesser, $cronRun);
                $synced++;
            } catch (RequestException|ConnectionException $exception) {
                Log::warning('GeoGuessr sync failed', [
                    'geoguesser_id' => $geoguesser->id,
                    'status' => $exception instanceof RequestException ? $exception->response?->status() : null,
                ]);
            }
        }

        return [
            'synced' => $synced,
            'skipped' => $skipped,
        ];
    }

    public function sync(Geoguesser $geoguesser, ?CronRun $cronRun = null): void
    {
        $ncfa = (string) $geoguesser->ncfa;

        $this->client->using('geoguessr_sync', $cronRun?->id, $geoguesser->id);

        $profile = $this->client->profile($ncfa);
        $this->syncProfile($geoguesser, $profile);
        $this->syncWeek($geoguesser, $ncfa, $this->client->weeklyDailyChallenges($ncfa), $this->progressFromProfile($profile));
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
     * @param  array<string, mixed>|null  $progress
     */
    private function syncWeek(Geoguesser $geoguesser, string $ncfa, array $days, ?array $progress): void
    {
        foreach ($days as $day) {
            $token = $day['challengeToken'] ?? null;
            $result = is_array($day['gameResult'] ?? null) ? $day['gameResult'] : null;

            if (! is_string($token) || $token === '' || $result === null) {
                continue;
            }

            $attemptedAt = $this->dateForDayOfWeek((int) ($day['dayOfWeek'] ?? 0));
            $values = [
                'attempted_at' => $attemptedAt,
                'total_score' => $result['totalScore'] ?? null,
                'geoguesser_guid' => $result['id'] ?? null,
                'total_distance' => isset($result['totalDistance']) ? (int) round((float) $result['totalDistance']) : null,
                'total_steps_count' => $result['totalStepsCount'] ?? null,
            ];

            if ($progress !== null && (($day['isToday'] ?? false) === true || $attemptedAt->isToday())) {
                $values['progress'] = $progress;
            }

            $challenge = $geoguesser->challenges()->updateOrCreate(
                ['challenge_token' => $token],
                $values,
            );

            $this->syncRounds($geoguesser, $challenge, $ncfa, $token);
        }
    }

    private function syncRounds(Geoguesser $geoguesser, GeoguesserChallenge $challenge, string $ncfa, string $token): void
    {
        try {
            $game = $this->client->challengeGame($ncfa, $token);
        } catch (RequestException|ConnectionException $exception) {
            Log::warning('GeoGuessr challenge game sync failed', [
                'geoguesser_id' => $geoguesser->id,
                'challenge_token' => $token,
                'status' => $exception instanceof RequestException ? $exception->response?->status() : null,
            ]);

            return;
        }

        $player = is_array($game['player'] ?? null) ? $game['player'] : [];
        $guesses = is_array($player['guesses'] ?? null) ? $player['guesses'] : [];
        $rounds = is_array($game['rounds'] ?? null) ? $game['rounds'] : [];

        if (($game['state'] ?? null) !== 'finished' && $guesses === []) {
            return;
        }

        $challenge->update([
            'game_token' => is_string($game['token'] ?? null) ? $game['token'] : $challenge->game_token,
            'map_name' => is_string($game['mapName'] ?? null) ? $game['mapName'] : $challenge->map_name,
            'total_score' => $this->intValue($player['totalScore']['amount'] ?? $player['totalScore'] ?? null) ?? $challenge->total_score,
            'total_distance' => isset($player['totalDistanceInMeters'])
                ? (int) round((float) $player['totalDistanceInMeters'])
                : $challenge->total_distance,
            'total_steps_count' => $this->intValue($player['totalStepsCount'] ?? null) ?? $challenge->total_steps_count,
        ]);

        foreach ($rounds as $index => $round) {
            if (! is_array($round)) {
                continue;
            }

            $guess = is_array($guesses[$index] ?? null) ? $guesses[$index] : [];
            $number = $index + 1;

            $challenge->rounds()->updateOrCreate(
                ['round_number' => $number],
                [
                    'actual_lat' => $this->floatValue($round['lat'] ?? null),
                    'actual_lng' => $this->floatValue($round['lng'] ?? null),
                    'guess_lat' => $this->floatValue($guess['lat'] ?? null),
                    'guess_lng' => $this->floatValue($guess['lng'] ?? null),
                    'score' => $this->intValue($guess['roundScoreInPoints'] ?? $guess['roundScore']['amount'] ?? null),
                    'percentage' => $this->floatValue($guess['roundScoreInPercentage'] ?? $guess['roundScore']['percentage'] ?? null),
                    'time' => $this->intValue($guess['time'] ?? null),
                    'steps_count' => $this->intValue($guess['stepsCount'] ?? null),
                    'distance_in_meters' => isset($guess['distanceInMeters'])
                        ? (int) round((float) $guess['distanceInMeters'])
                        : $this->intValue($guess['distance']['meters']['amount'] ?? null),
                    'timed_out' => is_bool($guess['timedOut'] ?? null) ? $guess['timedOut'] : null,
                    'timed_out_with_guess' => is_bool($guess['timedOutWithGuess'] ?? null) ? $guess['timedOutWithGuess'] : null,
                    'skipped_round' => is_bool($guess['skippedRound'] ?? null) ? $guess['skippedRound'] : null,
                    'heading' => $this->floatValue($round['heading'] ?? null),
                    'pitch' => $this->floatValue($round['pitch'] ?? null),
                    'zoom' => $this->intValue($round['zoom'] ?? null),
                    'pano_id' => is_string($round['panoId'] ?? null) ? $round['panoId'] : null,
                    'country_code' => is_string($round['streakLocationCode'] ?? null) ? $round['streakLocationCode'] : null,
                    'guess_country_code' => is_string($guess['streakLocationCode'] ?? null) ? $guess['streakLocationCode'] : null,
                    'started_at' => isset($round['startTime']) ? Carbon::parse($round['startTime']) : null,
                ],
            );
        }
    }

    private function intValue(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }

    private function floatValue(mixed $value): ?float
    {
        return is_numeric($value) ? (float) $value : null;
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

    /**
     * @param  array<string, mixed>  $profile
     * @return array<string, mixed>|null
     */
    private function progressFromProfile(array $profile): ?array
    {
        $user = is_array($profile['user'] ?? null) ? $profile['user'] : $profile;
        $progress = $user['progress'] ?? null;

        return is_array($progress) ? $progress : null;
    }

    private function alreadySyncedToday(Geoguesser $geoguesser): bool
    {
        return $geoguesser->challenges()
            ->whereDate('attempted_at', today())
            ->whereHas('rounds')
            ->exists();
    }

    private function dateForDayOfWeek(int $dayOfWeek): Carbon
    {
        return now()->startOfWeek(Carbon::SUNDAY)->addDays($dayOfWeek);
    }
}
