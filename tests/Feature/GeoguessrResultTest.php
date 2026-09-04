<?php

namespace Tests\Feature;

use App\Models\Geoguesser;
use App\Models\GeoguesserChallenge;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GeoguessrResultTest extends TestCase
{
    use RefreshDatabase;

    public function test_todays_results_are_listed_by_score(): void
    {
        $viewer = User::factory()->create(['name' => 'Viewer']);
        $first = User::factory()->create(['name' => 'Alex']);
        $second = User::factory()->create(['name' => 'Sam']);
        $yesterday = User::factory()->create(['name' => 'Yesterday Player']);

        GeoguesserChallenge::factory()->create([
            'geoguesser_id' => Geoguesser::factory()->create(['user_id' => $first->id]),
            'attempted_at' => now(),
            'total_score' => 18420,
        ]);

        GeoguesserChallenge::factory()->create([
            'geoguesser_id' => Geoguesser::factory()->create(['user_id' => $second->id]),
            'attempted_at' => now(),
            'total_score' => 12100,
        ]);

        GeoguesserChallenge::factory()->create([
            'geoguesser_id' => Geoguesser::factory()->create(['user_id' => $yesterday->id]),
            'attempted_at' => now()->subDay(),
            'total_score' => 25000,
        ]);

        $this->actingAs($viewer)
            ->get(route('geoguessr.index'))
            ->assertOk()
            ->assertSeeInOrder(['Alex', 'Sam'])
            ->assertSee('18,420')
            ->assertSee('12,100')
            ->assertSee('Today')
            ->assertSee('Graphs')
            ->assertDontSee('Update your score')
            ->assertDontSee('Log your score');
    }

    public function test_progress_heading_shows_level_and_xp(): void
    {
        $viewer = User::factory()->create();
        $geoguesser = Geoguesser::factory()->create(['user_id' => $viewer->id]);

        GeoguesserChallenge::factory()->create([
            'geoguesser_id' => $geoguesser->id,
            'attempted_at' => now(),
            'progress' => [
                'xp' => 5763,
                'level' => 18,
                'levelXp' => 5670,
                'nextLevel' => 19,
                'nextLevelXp' => 6390,
            ],
        ]);

        $this->actingAs($viewer)
            ->get(route('geoguessr.index'))
            ->assertOk()
            ->assertSee('Level')
            ->assertSee('18')
            ->assertSee('5,763')
            ->assertSee('6,390')
            ->assertSee('Next level 19')
            ->assertSee('XP');
    }

    public function test_graphs_include_historical_players_and_scores(): void
    {
        $viewer = User::factory()->create();
        $player = User::factory()->create(['name' => 'Historical Hank']);

        GeoguesserChallenge::factory()->create([
            'geoguesser_id' => Geoguesser::factory()->create([
                'user_id' => $player->id,
                'username' => 'HankOnGeo',
            ]),
            'attempted_at' => now()->subDays(3),
            'total_score' => 9900,
            'total_distance' => 2500000,
            'total_steps_count' => 88,
            'progress' => [
                'xp' => 4100,
                'level' => 16,
            ],
        ]);

        $this->actingAs($viewer)
            ->get(route('geoguessr.index', ['tab' => 'graphs']))
            ->assertOk()
            ->assertSee('Historical Hank (HankOnGeo)')
            ->assertSee('Everyone')
            ->assertSee('XP')
            ->assertSee('4100')
            ->assertSee('9900')
            ->assertSee('2500000');
    }
}
