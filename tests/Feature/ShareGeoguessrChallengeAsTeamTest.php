<?php

namespace Tests\Feature;

use App\Models\Geoguesser;
use App\Models\GeoguesserChallenge;
use App\Models\GeoguesserRound;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShareGeoguessrChallengeAsTeamTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_admin_sees_the_team_share_modal_on_the_profile(): void
    {
        $admin = User::factory()->create(['email' => 'mikaelclayton@gmail.com']);
        $source = Geoguesser::factory()->create([
            'user_id' => User::factory()->create(['name' => 'Alex']),
            'username' => 'AlexGeo',
            'is_active' => true,
        ]);
        Geoguesser::factory()->create([
            'user_id' => User::factory()->create(['name' => 'Sam']),
            'username' => 'SamGeo',
            'is_active' => true,
        ]);
        GeoguesserChallenge::factory()->create([
            'geoguesser_id' => $source->id,
            'attempted_at' => now(),
            'total_score' => 18420,
        ]);

        $this->actingAs($admin)
            ->get(route('profile.edit', ['tab' => 'geoguessr']))
            ->assertOk()
            ->assertSee('Sync as team')
            ->assertSee('Players')
            ->assertSee('Sam (SamGeo)');
    }

    public function test_other_users_do_not_see_the_team_share_modal(): void
    {
        $user = User::factory()->create(['email' => 'friend@example.com']);
        GeoguesserChallenge::factory()->create([
            'total_score' => 18420,
        ]);

        $this->actingAs($user)
            ->get(route('profile.edit', ['tab' => 'geoguessr']))
            ->assertOk()
            ->assertDontSee('Sync as team')
            ->assertDontSee('Copy this challenge and its rounds');
    }

    public function test_guests_cannot_share_a_challenge_as_a_team(): void
    {
        $this->postJson(route('profile.geoguessr.challenges.share'), [
            'challenge_id' => 1,
            'geoguesser_ids' => [1],
        ])->assertUnauthorized();
    }

    public function test_other_users_cannot_share_a_challenge_as_a_team(): void
    {
        $user = User::factory()->create(['email' => 'friend@example.com']);
        $challenge = GeoguesserChallenge::factory()->create();
        $target = Geoguesser::factory()->create(['is_active' => true]);

        $this->actingAs($user)
            ->postJson(route('profile.geoguessr.challenges.share'), [
                'challenge_id' => $challenge->id,
                'geoguesser_ids' => [$target->id],
            ])
            ->assertForbidden();
    }

    public function test_the_admin_can_share_a_challenge_and_rounds_with_selected_users(): void
    {
        $this->travelTo('2026-09-05 12:00:00');

        $admin = User::factory()->create(['email' => 'mikaelclayton@gmail.com']);
        $source = Geoguesser::factory()->create([
            'user_id' => User::factory()->create(['name' => 'Alex']),
            'username' => 'AlexGeo',
            'is_active' => true,
        ]);
        $sam = Geoguesser::factory()->create([
            'user_id' => User::factory()->create(['name' => 'Sam']),
            'username' => 'SamGeo',
            'is_active' => true,
        ]);
        $tess = Geoguesser::factory()->create([
            'user_id' => User::factory()->create(['name' => 'Tess']),
            'username' => 'TessGeo',
            'is_active' => true,
        ]);
        $challenge = GeoguesserChallenge::factory()->create([
            'geoguesser_id' => $source->id,
            'attempted_at' => now(),
            'challenge_token' => 'TeamDailyToken',
            'game_token' => 'TeamGameToken',
            'map_name' => 'World',
            'geoguesser_guid' => 'source-game-guid',
            'total_score' => 18918,
            'total_distance' => 2_726_300,
            'total_steps_count' => 59,
            'progress' => ['xp' => 6008, 'level' => 18],
            'is_done_as_team' => false,
        ]);
        GeoguesserRound::factory()->create([
            'geoguesser_challenge_id' => $challenge->id,
            'round_number' => 1,
            'actual_lat' => -10.6741219,
            'actual_lng' => -76.7747612,
            'guess_lat' => -7.55,
            'guess_lng' => -75.33,
            'score' => 3871,
            'percentage' => 77.42,
            'time' => 180,
            'steps_count' => 13,
            'distance_in_meters' => 381742,
            'country_code' => 'pe',
            'pano_id' => 'SourcePano',
        ]);

        $this->actingAs($admin)
            ->postJson(route('profile.geoguessr.challenges.share'), [
                'challenge_id' => $challenge->id,
                'geoguesser_ids' => [$sam->id, $tess->id],
            ])
            ->assertOk()
            ->assertJsonPath('isDoneAsTeam', true)
            ->assertJsonPath('message', 'Shared this challenge as a team with Sam (SamGeo), Tess (TessGeo).');

        $this->assertTrue($challenge->refresh()->is_done_as_team);

        foreach ([$sam, $tess] as $player) {
            $copy = GeoguesserChallenge::query()
                ->where('geoguesser_id', $player->id)
                ->where('challenge_token', 'TeamDailyToken')
                ->first();

            $this->assertNotNull($copy);
            $this->assertTrue($copy->is_done_as_team);
            $this->assertSame(18918, $copy->total_score);
            $this->assertSame(2_726_300, $copy->total_distance);
            $this->assertSame(59, $copy->total_steps_count);
            $this->assertSame('TeamGameToken', $copy->game_token);
            $this->assertNull($copy->progress);
            $this->assertSame(1, $copy->rounds()->count());
            $this->assertSame('SourcePano', $copy->rounds()->first()?->pano_id);
            $this->assertSame(3871, $copy->rounds()->first()?->score);
        }
    }

    public function test_the_admin_cannot_share_onto_a_player_who_already_played_that_day(): void
    {
        $this->travelTo('2026-09-05 12:00:00');

        $admin = User::factory()->create(['email' => 'mikaelclayton@gmail.com']);
        $source = Geoguesser::factory()->create(['is_active' => true]);
        $target = Geoguesser::factory()->create([
            'user_id' => User::factory()->create(['name' => 'Sam']),
            'username' => 'SamGeo',
            'is_active' => true,
        ]);
        $challenge = GeoguesserChallenge::factory()->create([
            'geoguesser_id' => $source->id,
            'attempted_at' => now(),
            'challenge_token' => 'SourceToken',
            'total_score' => 12000,
        ]);
        GeoguesserChallenge::factory()->create([
            'geoguesser_id' => $target->id,
            'attempted_at' => now()->setTime(8, 0),
            'challenge_token' => 'SamsOwnToken',
            'total_score' => 4000,
        ]);

        $this->actingAs($admin)
            ->postJson(route('profile.geoguessr.challenges.share'), [
                'challenge_id' => $challenge->id,
                'geoguesser_ids' => [$target->id],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('geoguesser_ids')
            ->assertJsonPath('errors.geoguesser_ids.0', 'Sam (SamGeo) already has a challenge for that day.');

        $this->assertFalse($challenge->refresh()->is_done_as_team);
        $this->assertSame(1, $target->challenges()->count());
    }

    public function test_the_admin_cannot_share_a_challenge_onto_its_owner(): void
    {
        $admin = User::factory()->create(['email' => 'mikaelclayton@gmail.com']);
        $player = Geoguesser::factory()->create(['is_active' => true]);
        $challenge = GeoguesserChallenge::factory()->create([
            'geoguesser_id' => $player->id,
            'attempted_at' => now(),
            'total_score' => 1000,
        ]);

        $this->actingAs($admin)
            ->postJson(route('profile.geoguessr.challenges.share'), [
                'challenge_id' => $challenge->id,
                'geoguesser_ids' => [$player->id],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('geoguesser_ids')
            ->assertJsonPath(
                'errors.geoguesser_ids.0',
                'Pick someone other than the player who logged this challenge.',
            );
    }

    public function test_sharing_as_a_team_requires_at_least_one_player(): void
    {
        $admin = User::factory()->create(['email' => 'mikaelclayton@gmail.com']);
        $challenge = GeoguesserChallenge::factory()->create([
            'attempted_at' => now(),
            'total_score' => 1000,
        ]);

        $this->actingAs($admin)
            ->postJson(route('profile.geoguessr.challenges.share'), [
                'challenge_id' => $challenge->id,
                'geoguesser_ids' => [],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('geoguesser_ids');
    }
}
