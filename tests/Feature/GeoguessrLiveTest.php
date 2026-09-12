<?php

namespace Tests\Feature;

use App\Models\Geoguesser;
use App\Models\GeoguesserChallenge;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GeoguessrLiveTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_poll_todays_results(): void
    {
        $this->getJson(route('geoguessr.live'))
            ->assertUnauthorized();
    }

    public function test_live_today_includes_a_new_score(): void
    {
        $this->travelTo('2026-09-11 12:00:00');

        $viewer = User::factory()->create();
        $player = User::factory()->create(['name' => 'Alex']);

        GeoguesserChallenge::factory()->create([
            'geoguesser_id' => Geoguesser::factory()->create(['user_id' => $player->id]),
            'attempted_at' => now(),
            'total_score' => 18420,
        ]);

        $response = $this->actingAs($viewer)
            ->getJson(route('geoguessr.live'));

        $response->assertOk();
        $this->assertNotSame('', $response->json('revision'));
        $this->assertStringContainsString('Alex', (string) $response->json('regions.today'));
        $this->assertStringContainsString('18,420', (string) $response->json('regions.today'));
    }

    public function test_matching_revision_omits_today_html(): void
    {
        $this->travelTo('2026-09-11 12:00:00');

        $viewer = User::factory()->create();
        $revision = $this->actingAs($viewer)
            ->getJson(route('geoguessr.live'))
            ->assertOk()
            ->json('revision');

        $this->actingAs($viewer)
            ->getJson(route('geoguessr.live', ['revision' => $revision]))
            ->assertOk()
            ->assertExactJson(['revision' => $revision]);
    }

    public function test_the_today_tab_is_wired_to_poll_live_results(): void
    {
        $viewer = User::factory()->create();

        $this->actingAs($viewer)
            ->get(route('geoguessr.index'))
            ->assertOk()
            ->assertSee('data-poll-url="'.e(route('geoguessr.live')).'"', false)
            ->assertSee('data-live-region="today"', false);
    }
}
