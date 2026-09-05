<?php

namespace Tests\Feature;

use App\Models\CronRun;
use App\Models\Geoguesser;
use App\Models\GeoguesserChallenge;
use App\Models\GeoguesserRound;
use App\Models\OutgoingApiCall;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SyncGeoguessrCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_syncs_active_geoguessers_with_an_ncfa(): void
    {
        $active = Geoguesser::factory()->create([
            'user_id' => User::factory(),
            'username' => 'OldName',
            'ncfa' => 'test-ncfa',
            'is_active' => true,
        ]);

        Geoguesser::factory()->create([
            'user_id' => User::factory(),
            'ncfa' => null,
            'is_active' => true,
        ]);

        Http::fake([
            'https://www.geoguessr.com/api/v3/profiles' => Http::response([
                'user' => [
                    'nick' => 'CoastalRiver217',
                    'id' => '6a5e21179d59314d29c99c7c',
                    'dailyChallengeProgress' => 15,
                    'progress' => [
                        'xp' => 5763,
                        'level' => 18,
                        'levelXp' => 5670,
                        'nextLevel' => 19,
                        'nextLevelXp' => 6390,
                    ],
                ],
            ]),
            'https://www.geoguessr.com/api/v3/challenges/daily-challenges/me/week' => Http::response([
                [
                    'dayOfWeek' => 4,
                    'challengeToken' => 'NJTAratoSkpaMgAd',
                    'isToday' => true,
                    'gameResult' => [
                        'id' => '6a5e21179d59314d29c99c7c',
                        'totalScore' => 13801,
                        'totalDistance' => 5612579.5,
                        'totalStepsCount' => 179,
                    ],
                ],
                [
                    'dayOfWeek' => 5,
                    'challengeToken' => null,
                    'gameResult' => null,
                ],
            ]),
            'https://www.geoguessr.com/api/v3/profiles/stats' => Http::response([
                'dailyChallengeStreak' => 6,
                'dailyChallengeCurrentStreak' => 4,
                'dailyChallengesRolling7Days' => [
                    [
                        'date' => '2026-09-03T13:27:23.1450000Z',
                        'challengeToken' => 'NJTAratoSkpaMgAd',
                        'totalScore' => 13801,
                        'totalDistance' => 5612579.5,
                    ],
                ],
            ]),
            'https://www.geoguessr.com/api/v3/challenges/NJTAratoSkpaMgAd' => Http::response($this->finishedGame('4sEJlv882H57mWwX')),
        ]);

        $this->artisan('geoguessr:sync')
            ->expectsOutput('Synced 1 GeoGuessr profile(s).')
            ->assertSuccessful();

        $active->refresh();

        $this->assertSame('CoastalRiver217', $active->username);
        $this->assertSame(15, $active->daily_challenge_progress);
        $this->assertSame(6, $active->daily_challenge_streak);
        $this->assertSame(4, $active->daily_challenge_current_streak);

        $this->assertDatabaseHas('geoguesser_challenges', [
            'geoguesser_id' => $active->id,
            'challenge_token' => 'NJTAratoSkpaMgAd',
            'total_score' => 13801,
            'geoguesser_guid' => '6a5e21179d59314d29c99c7c',
            'total_distance' => 5612580,
            'total_steps_count' => 179,
        ]);

        $this->assertDatabaseCount('geoguesser_challenges', 1);
        $this->assertDatabaseHas('geoguesser_challenges', [
            'challenge_token' => 'NJTAratoSkpaMgAd',
            'game_token' => '4sEJlv882H57mWwX',
            'map_name' => 'World',
        ]);
        $this->assertDatabaseCount('geoguesser_rounds', 2);
        $this->assertDatabaseHas('geoguesser_rounds', [
            'round_number' => 1,
            'score' => 3871,
            'steps_count' => 13,
            'country_code' => 'pe',
        ]);
        $this->assertSame(5763, $active->challenges()->first()?->progress['xp'] ?? null);
        $this->assertSame(18, $active->challenges()->first()?->progress['level'] ?? null);

        $this->assertDatabaseHas('cron_runs', [
            'command' => 'geoguessr:sync',
            'status' => 'success',
            'profiles_synced' => 1,
        ]);
        $this->assertGreaterThanOrEqual(0, CronRun::query()->value('duration_ms'));
        $this->assertDatabaseCount('outgoing_api_calls', 4);
        $this->assertDatabaseHas('outgoing_api_calls', [
            'source' => 'geoguessr_sync',
            'url' => 'https://www.geoguessr.com/api/v3/profiles',
            'status_code' => 200,
            'succeeded' => true,
            'geoguesser_id' => $active->id,
        ]);
        $this->assertDatabaseHas('outgoing_api_calls', [
            'source' => 'geoguessr_sync',
            'url' => 'https://www.geoguessr.com/api/v3/challenges/daily-challenges/me/week',
            'succeeded' => true,
        ]);
        $this->assertDatabaseHas('outgoing_api_calls', [
            'source' => 'geoguessr_sync',
            'method' => 'POST',
            'url' => 'https://www.geoguessr.com/api/v3/challenges/NJTAratoSkpaMgAd',
            'succeeded' => true,
        ]);
        $this->assertDatabaseHas('outgoing_api_calls', [
            'source' => 'geoguessr_sync',
            'url' => 'https://www.geoguessr.com/api/v3/profiles/stats',
            'succeeded' => true,
        ]);
        $this->assertSame(
            'CoastalRiver217',
            OutgoingApiCall::query()->where('url', 'https://www.geoguessr.com/api/v3/profiles')->first()?->response['user']['nick'] ?? null,
        );
        $this->assertSame(
            6,
            OutgoingApiCall::query()->where('url', 'https://www.geoguessr.com/api/v3/profiles/stats')->first()?->response['dailyChallengeStreak'] ?? null,
        );
    }

    public function test_it_skips_failed_geoguessr_requests(): void
    {
        Geoguesser::factory()->create([
            'user_id' => User::factory(),
            'ncfa' => 'expired',
            'is_active' => true,
        ]);

        Http::fake([
            'https://www.geoguessr.com/api/v3/profiles' => Http::response(['message' => 'Unauthorized'], 401),
        ]);

        $this->artisan('geoguessr:sync')
            ->expectsOutput('Synced 0 GeoGuessr profile(s).')
            ->assertSuccessful();

        $this->assertDatabaseHas('cron_runs', [
            'command' => 'geoguessr:sync',
            'status' => 'success',
            'profiles_synced' => 0,
        ]);
        $this->assertDatabaseHas('outgoing_api_calls', [
            'source' => 'geoguessr_sync',
            'url' => 'https://www.geoguessr.com/api/v3/profiles',
            'status_code' => 401,
            'succeeded' => false,
        ]);
        $this->assertDatabaseCount('outgoing_api_calls', 1);
        $this->assertSame(
            'Unauthorized',
            OutgoingApiCall::query()->first()?->response['message'] ?? null,
        );
    }

    public function test_it_snapshots_progress_on_todays_challenge_only(): void
    {
        $geoguesser = Geoguesser::factory()->create([
            'user_id' => User::factory(),
            'ncfa' => 'test-ncfa',
            'is_active' => true,
        ]);

        $past = GeoguesserChallenge::factory()->create([
            'geoguesser_id' => $geoguesser->id,
            'challenge_token' => 'old-token',
            'progress' => [
                'xp' => 4100,
                'level' => 16,
            ],
        ]);

        Http::fake([
            'https://www.geoguessr.com/api/v3/profiles' => Http::response([
                'user' => [
                    'nick' => 'CoastalRiver217',
                    'progress' => [
                        'xp' => 5763,
                        'level' => 18,
                    ],
                ],
            ]),
            'https://www.geoguessr.com/api/v3/challenges/daily-challenges/me/week' => Http::response([
                [
                    'dayOfWeek' => 4,
                    'challengeToken' => 'old-token',
                    'isToday' => false,
                    'gameResult' => [
                        'id' => 'old-guid',
                        'totalScore' => 10000,
                        'totalDistance' => 1000,
                        'totalStepsCount' => 10,
                    ],
                ],
                [
                    'dayOfWeek' => 5,
                    'challengeToken' => 'today-token',
                    'isToday' => true,
                    'gameResult' => [
                        'id' => 'today-guid',
                        'totalScore' => 13801,
                        'totalDistance' => 2000,
                        'totalStepsCount' => 20,
                    ],
                ],
            ]),
            'https://www.geoguessr.com/api/v3/profiles/stats' => Http::response([
                'dailyChallengeStreak' => 1,
                'dailyChallengeCurrentStreak' => 1,
                'dailyChallengesRolling7Days' => [],
            ]),
            'https://www.geoguessr.com/api/v3/challenges/old-token' => Http::response($this->finishedGame('old-game')),
            'https://www.geoguessr.com/api/v3/challenges/today-token' => Http::response($this->finishedGame('today-game')),
        ]);

        $this->artisan('geoguessr:sync')->assertSuccessful();

        $past->refresh();
        $today = $geoguesser->challenges()->where('challenge_token', 'today-token')->first();

        $this->assertSame(4100, $past->progress['xp'] ?? null);
        $this->assertSame(5763, $today?->progress['xp'] ?? null);
    }

    public function test_it_skips_profiles_already_synced_today(): void
    {
        $geoguesser = Geoguesser::factory()->create([
            'user_id' => User::factory(),
            'ncfa' => 'test-ncfa',
            'is_active' => true,
        ]);

        $challenge = GeoguesserChallenge::factory()->create([
            'geoguesser_id' => $geoguesser->id,
            'attempted_at' => now(),
            'challenge_token' => 'today-token',
        ]);

        GeoguesserRound::factory()->create([
            'geoguesser_challenge_id' => $challenge->id,
            'round_number' => 1,
        ]);

        Http::fake();

        $this->artisan('geoguessr:sync')
            ->expectsOutput('Synced 0 GeoGuessr profile(s).')
            ->expectsOutput('Skipped 1 already synced for today. Use --force to refresh.')
            ->assertSuccessful();

        Http::assertNothingSent();
        $this->assertDatabaseCount('outgoing_api_calls', 0);
        $this->assertDatabaseHas('cron_runs', [
            'command' => 'geoguessr:sync',
            'status' => 'success',
            'profiles_synced' => 0,
        ]);
    }

    public function test_force_syncs_profiles_already_synced_today(): void
    {
        $geoguesser = Geoguesser::factory()->create([
            'user_id' => User::factory(),
            'ncfa' => 'test-ncfa',
            'is_active' => true,
        ]);

        $challenge = GeoguesserChallenge::factory()->create([
            'geoguesser_id' => $geoguesser->id,
            'attempted_at' => now(),
            'challenge_token' => 'today-token',
        ]);

        GeoguesserRound::factory()->create([
            'geoguesser_challenge_id' => $challenge->id,
            'round_number' => 1,
        ]);

        Http::fake([
            'https://www.geoguessr.com/api/v3/profiles' => Http::response([
                'user' => [
                    'nick' => 'CoastalRiver217',
                    'progress' => ['xp' => 5763, 'level' => 18],
                ],
            ]),
            'https://www.geoguessr.com/api/v3/challenges/daily-challenges/me/week' => Http::response([
                [
                    'dayOfWeek' => now()->dayOfWeek,
                    'challengeToken' => 'today-token',
                    'isToday' => true,
                    'gameResult' => [
                        'id' => 'today-guid',
                        'totalScore' => 13801,
                        'totalDistance' => 2000,
                        'totalStepsCount' => 20,
                    ],
                ],
            ]),
            'https://www.geoguessr.com/api/v3/profiles/stats' => Http::response([
                'dailyChallengeStreak' => 1,
                'dailyChallengeCurrentStreak' => 1,
                'dailyChallengesRolling7Days' => [],
            ]),
            'https://www.geoguessr.com/api/v3/challenges/today-token' => Http::response($this->finishedGame('forced-game')),
        ]);

        $this->artisan('geoguessr:sync', ['--force' => true])
            ->expectsOutput('Synced 1 GeoGuessr profile(s).')
            ->assertSuccessful();

        Http::assertSentCount(4);
        $this->assertDatabaseHas('geoguesser_challenges', [
            'challenge_token' => 'today-token',
            'game_token' => 'forced-game',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function finishedGame(string $token): array
    {
        return [
            'token' => $token,
            'type' => 'challenge',
            'state' => 'finished',
            'mapName' => 'World',
            'roundCount' => 2,
            'rounds' => [
                [
                    'lat' => -10.674121905008892,
                    'lng' => -76.77476120151215,
                    'heading' => 301.66,
                    'pitch' => -1.0,
                    'zoom' => 0,
                    'panoId' => 'abc',
                    'streakLocationCode' => 'pe',
                    'startTime' => '2026-09-04T11:08:44.3200000Z',
                ],
                [
                    'lat' => 35.29162732670191,
                    'lng' => 10.707130652657813,
                    'heading' => 213.89,
                    'pitch' => -0.5,
                    'zoom' => 0,
                    'panoId' => 'def',
                    'streakLocationCode' => 'tn',
                    'startTime' => '2026-09-04T11:11:49.7300000Z',
                ],
            ],
            'player' => [
                'totalScore' => ['amount' => '13801'],
                'totalDistanceInMeters' => 5612579.5,
                'totalStepsCount' => 179,
                'guesses' => [
                    [
                        'lat' => -7.55017282442357,
                        'lng' => -75.33262361233172,
                        'timedOut' => false,
                        'timedOutWithGuess' => true,
                        'skippedRound' => false,
                        'roundScoreInPoints' => 3871,
                        'roundScoreInPercentage' => 77.42,
                        'distanceInMeters' => 381742.3049290026,
                        'stepsCount' => 13,
                        'time' => 180,
                    ],
                    [
                        'lat' => 35.07422686446614,
                        'lng' => 9.414807450049064,
                        'timedOut' => false,
                        'timedOutWithGuess' => false,
                        'skippedRound' => false,
                        'roundScoreInPoints' => 4614,
                        'roundScoreInPercentage' => 92.28,
                        'distanceInMeters' => 119909.2304899298,
                        'stepsCount' => 8,
                        'time' => 93,
                    ],
                ],
            ],
        ];
    }
}
