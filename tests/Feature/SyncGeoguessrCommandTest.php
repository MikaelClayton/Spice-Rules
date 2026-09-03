<?php

namespace Tests\Feature;

use App\Models\Geoguesser;
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
        ]);

        $this->artisan('geoguessr:sync')
            ->expectsOutput('Synced 1 GeoGuessr profile(s).')
            ->assertSuccessful();

        $active->refresh();

        $this->assertSame('CoastalRiver217', $active->username);
        $this->assertSame(15, $active->daily_challenge_progress);
        $this->assertSame(18, $active->progress['level']);
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
    }
}
