<?php

namespace Tests\Feature;

use App\Models\SpirdlePlay;
use App\Models\SpirdlePuzzle;
use App\Models\SpirdleWord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SpirdleLiveTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_poll_todays_results(): void
    {
        $this->getJson(route('spirdle.live'))
            ->assertUnauthorized();
    }

    public function test_live_today_includes_a_new_finish(): void
    {
        $this->travelTo('2026-09-16 12:00:00');

        $viewer = User::factory()->create();
        $player = User::factory()->create(['name' => 'Ada']);
        $puzzle = SpirdlePuzzle::factory()->create([
            'spirdle_word_id' => SpirdleWord::factory()->create(['word' => 'crane']),
            'play_date' => '2026-09-16',
        ]);

        SpirdlePlay::factory()->finished(3, true, 12000)->create([
            'user_id' => $player->id,
            'spirdle_puzzle_id' => $puzzle->id,
        ]);

        $response = $this->actingAs($viewer)->getJson(route('spirdle.live'));

        $response->assertOk();
        $this->assertNotSame('', $response->json('revision'));
        $this->assertStringContainsString('Ada', (string) $response->json('regions.today'));
        $this->assertStringContainsString('3 guesses', (string) $response->json('regions.today'));
    }

    public function test_matching_revision_omits_today_html(): void
    {
        $this->travelTo('2026-09-16 12:00:00');

        $viewer = User::factory()->create();
        $revision = $this->actingAs($viewer)
            ->getJson(route('spirdle.live'))
            ->assertOk()
            ->json('revision');

        $this->actingAs($viewer)
            ->getJson(route('spirdle.live', ['revision' => $revision]))
            ->assertOk()
            ->assertExactJson(['revision' => $revision]);
    }
}
