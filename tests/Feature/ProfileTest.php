<?php

namespace Tests\Feature;

use App\Models\Geoguesser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_from_the_profile(): void
    {
        $this->get(route('profile.edit'))->assertRedirect(route('login'));
    }

    public function test_authenticated_users_can_view_the_geoguessr_profile_section(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertSee('GeoGuessr')
            ->assertSee('_ncfa')
            ->assertSee('Active')
            ->assertSee('Test')
            ->assertSee('How to get your _ncfa')
            ->assertSee('youtube.com/watch?v=XSfTz9SZjTM');
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
            ->assertRedirect(route('profile.edit'));

        $this->assertDatabaseHas('geoguessers', [
            'user_id' => $user->id,
            'ncfa' => 'test-ncfa-token',
            'username' => 'CoastalRiver217',
            'daily_challenge_progress' => 15,
            'is_active' => true,
        ]);
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
            ->assertRedirect(route('profile.edit'))
            ->assertSessionHasErrors('ncfa');

        $this->assertDatabaseHas('geoguessers', [
            'user_id' => $user->id,
            'is_active' => false,
        ]);
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
            ->assertRedirect(route('profile.edit'))
            ->assertSessionHasErrors('ncfa');

        $this->assertDatabaseHas('geoguessers', [
            'user_id' => $user->id,
            'ncfa' => 'any-token',
        ]);
    }
}
