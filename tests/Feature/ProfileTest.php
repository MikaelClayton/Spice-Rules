<?php

namespace Tests\Feature;

use App\Models\Geoguesser;
use App\Models\GeoguesserChallenge;
use App\Models\OutgoingApiCall;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Sequence;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_from_the_profile(): void
    {
        $this->get(route('profile.edit'))->assertRedirect(route('login'));
    }

    public function test_authenticated_users_can_view_account_and_geoguessr_tabs(): void
    {
        $user = User::factory()->create([
            'name' => 'Mikael Clayton',
            'email' => 'mikael@example.com',
        ]);

        $this->actingAs($user)
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertSee('Account')
            ->assertSee('Your details')
            ->assertSee('Mikael Clayton')
            ->assertSee('mikael@example.com')
            ->assertSee('Board colour')
            ->assertSee('GeoGuessr')
            ->assertSee('_ncfa')
            ->assertSee('Active')
            ->assertSee('Test')
            ->assertSee('How to get your _ncfa')
            ->assertSee('youtube.com/watch?v=XSfTz9SZjTM')
            ->assertDontSee('Sync scores')
            ->assertDontSee('Challenges by player');
    }

    public function test_users_can_update_their_details(): void
    {
        $user = User::factory()->create([
            'name' => 'Old Name',
            'email' => 'old@example.com',
        ]);

        $this->actingAs($user)
            ->patch(route('profile.update'), [
                'name' => 'New Name',
                'email' => 'new@example.com',
                'color' => '#2A9D8F',
            ])
            ->assertRedirect(route('profile.edit'));

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'New Name',
            'email' => 'new@example.com',
            'color' => '#2A9D8F',
        ]);
    }

    public function test_users_can_change_their_password(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patch(route('profile.update'), [
                'name' => $user->name,
                'email' => $user->email,
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ])
            ->assertRedirect(route('profile.edit'));

        $user->refresh();

        $this->assertTrue(Hash::check('new-password', $user->password));
    }

    public function test_email_must_stay_unique(): void
    {
        $user = User::factory()->create(['email' => 'me@example.com']);
        User::factory()->create(['email' => 'taken@example.com']);

        $this->actingAs($user)
            ->from(route('profile.edit'))
            ->patch(route('profile.update'), [
                'name' => $user->name,
                'email' => 'taken@example.com',
            ])
            ->assertRedirect(route('profile.edit'))
            ->assertSessionHasErrors('email');
    }

    public function test_saved_ncfa_is_shown_on_the_profile(): void
    {
        $user = User::factory()->create();

        Geoguesser::factory()->create([
            'user_id' => $user->id,
            'ncfa' => 'visible-ncfa-token',
        ]);

        $this->actingAs($user)
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertSee('visible-ncfa-token');
    }

    public function test_a_successful_test_activates_geoguessr_and_saves_profile_data(): void
    {
        $user = User::factory()->create();

        Http::fake([
            'https://www.geoguessr.com/api/v3/profiles' => Http::response([
                'user' => [
                    'nick' => 'CoastalRiver217',
                    'dailyChallengeProgress' => 15,
                    'progress' => [
                        'xp' => 5763,
                        'level' => 18,
                    ],
                ],
            ]),
        ]);

        $this->actingAs($user)
            ->post(route('profile.geoguessr.update'), [
                'ncfa' => '  test-ncfa-token  ',
            ])
            ->assertRedirect(route('profile.edit', ['tab' => 'geoguessr']));

        $this->assertDatabaseHas('geoguessers', [
            'user_id' => $user->id,
            'ncfa' => 'test-ncfa-token',
            'username' => 'CoastalRiver217',
            'daily_challenge_progress' => 15,
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('outgoing_api_calls', [
            'source' => 'cookie_test',
            'method' => 'GET',
            'url' => 'https://www.geoguessr.com/api/v3/profiles',
            'status_code' => 200,
            'succeeded' => true,
        ]);
        $this->assertDatabaseCount('outgoing_api_calls', 1);
        $this->assertSame(
            'CoastalRiver217',
            OutgoingApiCall::query()->first()?->response['user']['nick'] ?? null,
        );
    }

    public function test_a_failed_test_does_not_activate_geoguessr(): void
    {
        $user = User::factory()->create();

        Http::fake([
            'https://www.geoguessr.com/api/v3/profiles' => Http::response(['message' => 'Unauthorized'], 401),
        ]);

        $this->actingAs($user)
            ->from(route('profile.edit'))
            ->post(route('profile.geoguessr.update'), [
                'ncfa' => 'bad-token',
            ])
            ->assertRedirect(route('profile.edit', ['tab' => 'geoguessr']))
            ->assertSessionHasErrors('ncfa');

        $this->assertDatabaseHas('geoguessers', [
            'user_id' => $user->id,
            'is_active' => false,
        ]);

        $this->assertDatabaseHas('outgoing_api_calls', [
            'source' => 'cookie_test',
            'url' => 'https://www.geoguessr.com/api/v3/profiles',
            'status_code' => 401,
            'succeeded' => false,
        ]);
        $this->assertSame(
            'Unauthorized',
            OutgoingApiCall::query()->first()?->response['message'] ?? null,
        );
    }

    public function test_a_connection_failure_does_not_crash_the_profile(): void
    {
        $user = User::factory()->create();

        Http::fake(function () {
            throw new ConnectionException('CONNECT tunnel failed, response 403');
        });

        $this->actingAs($user)
            ->from(route('profile.edit'))
            ->post(route('profile.geoguessr.update'), [
                'ncfa' => '_ncfa=any-token',
            ])
            ->assertRedirect(route('profile.edit', ['tab' => 'geoguessr']))
            ->assertSessionHasErrors('ncfa');

        $this->assertDatabaseHas('geoguessers', [
            'user_id' => $user->id,
            'ncfa' => 'any-token',
        ]);

        $this->assertDatabaseHas('outgoing_api_calls', [
            'source' => 'cookie_test',
            'url' => 'https://www.geoguessr.com/api/v3/profiles',
            'status_code' => null,
            'succeeded' => false,
        ]);
    }

    public function test_active_geoguessr_users_see_the_sync_button(): void
    {
        $user = User::factory()->create();

        Geoguesser::factory()->create([
            'user_id' => $user->id,
            'ncfa' => 'sync-ncfa',
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->get(route('profile.edit', ['tab' => 'geoguessr']))
            ->assertOk()
            ->assertSee('Sync scores');
    }

    public function test_users_can_sync_their_geoguessr_scores(): void
    {
        $user = User::factory()->create();
        $geoguesser = Geoguesser::factory()->create([
            'user_id' => $user->id,
            'username' => 'OldName',
            'ncfa' => 'sync-ncfa',
            'is_active' => true,
        ]);

        Http::fake([
            'https://www.geoguessr.com/api/v3/profiles' => Http::response([
                'user' => [
                    'nick' => 'CoastalRiver217',
                    'dailyChallengeProgress' => 15,
                    'progress' => [
                        'xp' => 5763,
                        'level' => 18,
                    ],
                ],
            ]),
            'https://www.geoguessr.com/api/v3/challenges/daily-challenges/me/week' => Http::response([
                [
                    'dayOfWeek' => now()->dayOfWeek,
                    'challengeToken' => 'NJTAratoSkpaMgAd',
                    'isToday' => true,
                    'gameResult' => [
                        'id' => '6a5e21179d59314d29c99c7c',
                        'totalScore' => 13801,
                        'totalDistance' => 5612579.5,
                        'totalStepsCount' => 179,
                    ],
                ],
            ]),
            'https://www.geoguessr.com/api/v3/profiles/stats' => Http::response([
                'dailyChallengeStreak' => 6,
                'dailyChallengeCurrentStreak' => 4,
                'dailyChallengesRolling7Days' => [],
            ]),
            'https://www.geoguessr.com/api/v3/challenges/NJTAratoSkpaMgAd' => Http::response([
                'token' => '4sEJlv882H57mWwX',
                'state' => 'finished',
                'mapName' => 'World',
                'rounds' => [
                    [
                        'lat' => -10.67,
                        'lng' => -76.77,
                        'panoId' => 'abc',
                        'streakLocationCode' => 'pe',
                    ],
                ],
                'player' => [
                    'totalScore' => ['amount' => '13801'],
                    'totalDistanceInMeters' => 5612579.5,
                    'totalStepsCount' => 179,
                    'guesses' => [
                        [
                            'lat' => -7.55,
                            'lng' => -75.33,
                            'roundScoreInPoints' => 3871,
                            'stepsCount' => 13,
                        ],
                    ],
                ],
            ]),
        ]);

        $this->actingAs($user)
            ->post(route('profile.geoguessr.sync'))
            ->assertRedirect(route('profile.edit', ['tab' => 'geoguessr']))
            ->assertSessionHas('status', 'GeoGuessr scores were synced.');

        $this->assertDatabaseHas('geoguessers', [
            'id' => $geoguesser->id,
            'username' => 'CoastalRiver217',
            'daily_challenge_streak' => 6,
        ]);
        $this->assertDatabaseHas('geoguesser_challenges', [
            'geoguesser_id' => $geoguesser->id,
            'challenge_token' => 'NJTAratoSkpaMgAd',
            'total_score' => 13801,
        ]);
        $this->assertDatabaseHas('geoguesser_rounds', [
            'round_number' => 1,
            'score' => 3871,
        ]);
    }

    public function test_sync_requires_an_active_geoguessr_cookie(): void
    {
        $user = User::factory()->create();

        Http::fake();

        $this->actingAs($user)
            ->from(route('profile.edit', ['tab' => 'geoguessr']))
            ->post(route('profile.geoguessr.sync'))
            ->assertRedirect(route('profile.edit', ['tab' => 'geoguessr']))
            ->assertSessionHasErrors('sync');

        Http::assertNothingSent();
    }

    public function test_a_rejected_sync_cookie_shows_an_error(): void
    {
        $user = User::factory()->create();

        Geoguesser::factory()->create([
            'user_id' => $user->id,
            'ncfa' => 'expired',
            'is_active' => true,
        ]);

        Http::fake([
            'https://www.geoguessr.com/api/v3/profiles' => Http::response(['message' => 'Unauthorized'], 401),
        ]);

        $this->actingAs($user)
            ->from(route('profile.edit', ['tab' => 'geoguessr']))
            ->post(route('profile.geoguessr.sync'))
            ->assertRedirect(route('profile.edit', ['tab' => 'geoguessr']))
            ->assertSessionHasErrors('sync');
    }

    public function test_the_admin_sees_a_filterable_challenge_grid_on_the_geoguessr_tab(): void
    {
        $this->travelTo('2026-09-05 12:00:00');

        $admin = User::factory()->create(['email' => 'mikaelclayton@gmail.com']);
        $alex = User::factory()->create(['name' => 'Alex']);
        $sam = User::factory()->create(['name' => 'Sam']);
        $alexGeo = Geoguesser::factory()->create([
            'user_id' => $alex->id,
            'username' => 'AlexGeo',
        ]);
        $samGeo = Geoguesser::factory()->create([
            'user_id' => $sam->id,
            'username' => 'SamGeo',
        ]);

        GeoguesserChallenge::factory()->create([
            'geoguesser_id' => $alexGeo->id,
            'attempted_at' => now(),
            'map_name' => 'World',
            'total_score' => 18420,
            'total_distance' => 1_250_000,
            'total_steps_count' => 88,
        ]);
        GeoguesserChallenge::factory()->create([
            'geoguesser_id' => $samGeo->id,
            'attempted_at' => now()->subDay(),
            'map_name' => 'A Community World',
            'total_score' => 12100,
            'total_distance' => 2_000_000,
            'total_steps_count' => 41,
        ]);

        $this->actingAs($admin)
            ->get(route('profile.edit', ['tab' => 'geoguessr']))
            ->assertOk()
            ->assertSee('_ncfa')
            ->assertSee('How to get your _ncfa')
            ->assertSee('Challenges by player')
            ->assertSee('Everyone')
            ->assertSee('Alex (AlexGeo)')
            ->assertSee('Sam (SamGeo)')
            ->assertSee('18,420')
            ->assertSee('12,100')
            ->assertSee('1,250.0 km')
            ->assertSee('88 steps')
            ->assertSee('A Community World')
            ->assertSee('Sep 5, 2026')
            ->assertSee('Sep 4, 2026')
            ->assertSee('Sync as team');
    }

    public function test_other_users_do_not_see_the_challenge_grid_on_the_profile(): void
    {
        $user = User::factory()->create(['email' => 'friend@example.com']);
        $alex = User::factory()->create(['name' => 'Secret Alex']);

        GeoguesserChallenge::factory()->create([
            'geoguesser_id' => Geoguesser::factory()->create([
                'user_id' => $alex->id,
                'username' => 'HiddenGeo',
            ]),
            'attempted_at' => now(),
            'total_score' => 24999,
        ]);

        $this->actingAs($user)
            ->get(route('profile.edit', ['tab' => 'geoguessr']))
            ->assertOk()
            ->assertSee('_ncfa')
            ->assertDontSee('Challenges by player')
            ->assertDontSee('Everyone')
            ->assertDontSee('Secret Alex')
            ->assertDontSee('24,999');
    }

    public function test_the_admin_challenge_grid_escapes_player_names(): void
    {
        $admin = User::factory()->create(['email' => 'mikaelclayton@gmail.com']);
        $player = User::factory()->create(['name' => "<script>alert('xss')</script>"]);

        GeoguesserChallenge::factory()->create([
            'geoguesser_id' => Geoguesser::factory()->create([
                'user_id' => $player->id,
                'username' => 'SafeNick',
            ]),
            'attempted_at' => now(),
            'total_score' => 1000,
        ]);

        $this->actingAs($admin)
            ->get(route('profile.edit', ['tab' => 'geoguessr']))
            ->assertOk()
            ->assertDontSee("<script>alert('xss')</script>", false)
            ->assertSee("<script>alert('xss')</script>");
    }

    public function test_the_admin_sees_an_empty_challenge_grid_when_nobody_has_played(): void
    {
        $admin = User::factory()->create(['email' => 'mikaelclayton@gmail.com']);

        $this->actingAs($admin)
            ->get(route('profile.edit', ['tab' => 'geoguessr']))
            ->assertOk()
            ->assertSee('Challenges by player')
            ->assertSee('No challenges have been synced yet.')
            ->assertDontSee('Everyone')
            ->assertDontSee('Load more');
    }

    public function test_the_admin_challenge_grid_shows_twenty_five_challenges_and_a_load_more_button(): void
    {
        $this->travelTo('2026-09-05 12:00:00');

        $admin = User::factory()->create(['email' => 'mikaelclayton@gmail.com']);
        $player = Geoguesser::factory()->create([
            'user_id' => User::factory()->create(['name' => 'Alex']),
            'username' => 'AlexGeo',
        ]);

        $this->createPagedChallenges($player);

        $this->actingAs($admin)
            ->get(route('profile.edit', ['tab' => 'geoguessr']))
            ->assertOk()
            ->assertSee('Challenges by player')
            ->assertSee('Load more')
            ->assertSee('30,000')
            ->assertSee('29,976')
            ->assertDontSee('29,975');
    }

    public function test_the_admin_can_load_the_next_page_of_challenges(): void
    {
        $this->travelTo('2026-09-05 12:00:00');

        $admin = User::factory()->create(['email' => 'mikaelclayton@gmail.com']);
        $player = Geoguesser::factory()->create();

        $this->createPagedChallenges($player);

        $this->actingAs($admin)
            ->getJson(route('profile.geoguessr.challenges', ['page' => 2, 'player' => 'all']))
            ->assertOk()
            ->assertJsonPath('hasMore', false)
            ->assertJsonCount(1, 'challenges')
            ->assertJsonPath('challenges.0.score', 29975);
    }

    public function test_the_admin_can_browse_challenges_for_one_player(): void
    {
        $admin = User::factory()->create(['email' => 'mikaelclayton@gmail.com']);
        $alex = Geoguesser::factory()->create([
            'user_id' => User::factory()->create(['name' => 'Alex']),
        ]);
        $sam = Geoguesser::factory()->create([
            'user_id' => User::factory()->create(['name' => 'Sam']),
        ]);

        $this->createPagedChallenges($alex);
        GeoguesserChallenge::factory()->create([
            'geoguesser_id' => $sam->id,
            'attempted_at' => now(),
            'total_score' => 11111,
        ]);

        $this->actingAs($admin)
            ->getJson(route('profile.geoguessr.challenges', ['player' => $sam->id]))
            ->assertOk()
            ->assertJsonPath('hasMore', false)
            ->assertJsonCount(1, 'challenges')
            ->assertJsonPath('challenges.0.score', 11111)
            ->assertJsonPath('challenges.0.playerId', $sam->id);
    }

    public function test_other_users_cannot_browse_challenge_pages(): void
    {
        $user = User::factory()->create(['email' => 'friend@example.com']);
        GeoguesserChallenge::factory()->create();

        $this->actingAs($user)
            ->getJson(route('profile.geoguessr.challenges'))
            ->assertForbidden();
    }

    public function test_guests_cannot_browse_challenge_pages(): void
    {
        $this->getJson(route('profile.geoguessr.challenges'))
            ->assertUnauthorized();
    }

    private function createPagedChallenges(Geoguesser $player): void
    {
        GeoguesserChallenge::factory()
            ->count(26)
            ->recycle($player)
            ->sequence(fn (Sequence $sequence): array => [
                'attempted_at' => now()->subDays($sequence->index),
                'total_score' => 30000 - $sequence->index,
                'challenge_token' => 'daily-'.$sequence->index,
            ])
            ->create();
    }
}
