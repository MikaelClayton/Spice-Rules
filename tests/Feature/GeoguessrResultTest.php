<?php

namespace Tests\Feature;

use App\Models\Geoguesser;
use App\Models\GeoguesserChallenge;
use App\Models\GeoguesserRound;
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
            ->assertSeeInOrder(['Today', 'Challenges', 'Graphs'])
            ->assertDontSee('Duplicate a daily')
            ->assertDontSee('Update your score')
            ->assertDontSee('Log your score');
    }

    public function test_today_uses_clickable_reward_emojis_without_a_you_badge(): void
    {
        $viewer = User::factory()->create(['name' => 'Viewer']);
        $close = User::factory()->create(['name' => 'Close Casey']);
        $far = User::factory()->create(['name' => 'Far Frankie']);

        GeoguesserChallenge::factory()->create([
            'geoguesser_id' => Geoguesser::factory()->create(['user_id' => $viewer->id]),
            'attempted_at' => now(),
            'total_score' => 15000,
            'total_distance' => 500_000,
            'total_steps_count' => 50,
        ]);

        GeoguesserChallenge::factory()->create([
            'geoguesser_id' => Geoguesser::factory()->create(['user_id' => $close->id]),
            'attempted_at' => now(),
            'total_score' => 18420,
            'total_distance' => 100_000,
            'total_steps_count' => 10,
        ]);

        GeoguesserChallenge::factory()->create([
            'geoguesser_id' => Geoguesser::factory()->create(['user_id' => $far->id]),
            'attempted_at' => now(),
            'total_score' => 2100,
            'total_distance' => 2_000_000,
            'total_steps_count' => 200,
        ]);

        $this->actingAs($viewer)
            ->get(route('geoguessr.index'))
            ->assertOk()
            ->assertSee('ring-2 ring-primary', false)
            ->assertDontSee('>You</span>', false)
            ->assertSee('data-reward="💪 Closest to target · 100.0 km"', false)
            ->assertSee('data-reward="💩 Furthest from target · 2,000.0 km"', false)
            ->assertSee('data-reward="♿ Least steps · 10"', false)
            ->assertSee('data-reward="🏃 Most steps · 200"', false);
    }

    public function test_tied_scores_share_the_same_rank(): void
    {
        $viewer = User::factory()->create(['name' => 'Viewer']);

        GeoguesserChallenge::factory()->create([
            'geoguesser_id' => Geoguesser::factory()->create([
                'user_id' => User::factory()->create(['name' => 'Alex']),
            ]),
            'attempted_at' => now(),
            'total_score' => 19170,
        ]);
        GeoguesserChallenge::factory()->create([
            'geoguesser_id' => Geoguesser::factory()->create([
                'user_id' => User::factory()->create(['name' => 'Bronwyn']),
            ]),
            'attempted_at' => now(),
            'total_score' => 19170,
        ]);
        GeoguesserChallenge::factory()->create([
            'geoguesser_id' => Geoguesser::factory()->create([
                'user_id' => User::factory()->create(['name' => 'Melissa']),
            ]),
            'attempted_at' => now(),
            'total_score' => 19170,
        ]);
        GeoguesserChallenge::factory()->create([
            'geoguesser_id' => Geoguesser::factory()->create([
                'user_id' => User::factory()->create(['name' => 'Nikhil']),
            ]),
            'attempted_at' => now(),
            'total_score' => 14396,
        ]);

        $this->actingAs($viewer)
            ->get(route('geoguessr.index'))
            ->assertOk()
            ->assertSeeInOrder(['Alex', 'Bronwyn', 'Melissa', 'Nikhil'])
            ->assertSeeInOrder(['>1</span>', '>1</span>', '>1</span>', '>4</span>'], false)
            ->assertDontSee('>2</span>', false)
            ->assertDontSee('>3</span>', false);
    }

    public function test_team_sync_shows_only_a_handshake_emoji(): void
    {
        $viewer = User::factory()->create(['name' => 'Viewer']);

        GeoguesserChallenge::factory()->create([
            'geoguesser_id' => Geoguesser::factory()->create([
                'user_id' => User::factory()->create(['name' => 'Alex']),
            ]),
            'attempted_at' => now(),
            'total_score' => 19170,
            'total_distance' => 100_000,
            'total_steps_count' => 200,
            'is_done_as_team' => true,
        ]);
        GeoguesserChallenge::factory()->create([
            'geoguesser_id' => Geoguesser::factory()->create([
                'user_id' => User::factory()->create(['name' => 'Sam']),
            ]),
            'attempted_at' => now(),
            'total_score' => 12000,
            'total_distance' => 2_000_000,
            'total_steps_count' => 10,
            'is_done_as_team' => false,
        ]);

        $this->actingAs($viewer)
            ->get(route('geoguessr.index'))
            ->assertOk()
            ->assertSee('data-reward="🤝 Played as a team"', false)
            ->assertDontSee('data-reward="💪 Closest to target · 100.0 km"', false)
            ->assertDontSee('data-reward="🏃 Most steps · 200"', false)
            ->assertSee('data-reward="💩 Furthest from target · 2,000.0 km"', false)
            ->assertSee('data-reward="♿ Least steps · 10"', false);
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

    public function test_level_bar_uses_previous_level_xp_when_start_is_missing(): void
    {
        $viewer = User::factory()->create();
        $geoguesser = Geoguesser::factory()->create(['user_id' => $viewer->id]);

        GeoguesserChallenge::factory()->create([
            'geoguesser_id' => $geoguesser->id,
            'attempted_at' => now()->subDay(),
            'progress' => [
                'xp' => 5600,
                'level' => 17,
                'nextLevel' => 18,
                'nextLevelXp' => 5670,
            ],
        ]);

        GeoguesserChallenge::factory()->create([
            'geoguesser_id' => $geoguesser->id,
            'attempted_at' => now(),
            'progress' => [
                'xp' => 6030,
                'level' => 18,
                'nextLevel' => 19,
                'nextLevelXp' => 6390,
            ],
        ]);

        $this->actingAs($viewer)
            ->get(route('geoguessr.index'))
            ->assertOk()
            ->assertSee('value="50"', false);
    }

    public function test_level_bar_defaults_to_the_middle_without_history(): void
    {
        $viewer = User::factory()->create();
        $geoguesser = Geoguesser::factory()->create(['user_id' => $viewer->id]);

        GeoguesserChallenge::factory()->create([
            'geoguesser_id' => $geoguesser->id,
            'attempted_at' => now(),
            'progress' => [
                'xp' => 6030,
                'level' => 18,
                'nextLevel' => 19,
                'nextLevelXp' => 6390,
            ],
        ]);

        $this->actingAs($viewer)
            ->get(route('geoguessr.index'))
            ->assertOk()
            ->assertSee('value="50"', false);
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

    public function test_graphs_tab_renders_insight_sections(): void
    {
        $viewer = User::factory()->create();

        $this->actingAs($viewer)
            ->get(route('geoguessr.index', ['tab' => 'graphs']))
            ->assertOk()
            ->assertSee('Score calendar')
            ->assertSee('Round 1-5')
            ->assertSee('Head-to-head')
            ->assertSee('Country heat')
            ->assertSee('Guess heat')
            ->assertSee('Continent leaderboard')
            ->assertSee('Country leaderboard')
            ->assertSee('data-insight-map-wrap="countries"', false)
            ->assertSee('data-insight-map-wrap="guesses"', false)
            ->assertSee('data-map-fullscreen', false);
    }

    public function test_graphs_omit_todays_locations_until_the_viewer_plays(): void
    {
        $viewer = User::factory()->create();
        $other = User::factory()->create(['name' => 'Other Player']);
        $geoguesser = Geoguesser::factory()->create([
            'user_id' => $other->id,
            'username' => 'OtherOnGeo',
        ]);
        $today = GeoguesserChallenge::factory()->create([
            'geoguesser_id' => $geoguesser->id,
            'challenge_token' => 'TodayInsightToken',
            'attempted_at' => now(),
            'total_score' => 5000,
        ]);

        GeoguesserRound::factory()->create([
            'geoguesser_challenge_id' => $today->id,
            'round_number' => 1,
            'actual_lat' => 12.3456789,
            'actual_lng' => 98.7654321,
            'guess_lat' => 11.111,
            'guess_lng' => 22.222,
            'score' => 4321,
            'country_code' => 'jp',
        ]);

        $yesterday = GeoguesserChallenge::factory()->create([
            'geoguesser_id' => $geoguesser->id,
            'challenge_token' => 'YesterdayInsightToken',
            'attempted_at' => now()->subDay(),
            'total_score' => 3871,
        ]);

        GeoguesserRound::factory()->create([
            'geoguesser_challenge_id' => $yesterday->id,
            'round_number' => 1,
            'actual_lat' => -10.6741219,
            'actual_lng' => -76.7747612,
            'guess_lat' => -7.5501728,
            'guess_lng' => -75.3326236,
            'score' => 3871,
            'country_code' => 'pe',
        ]);

        $this->actingAs($viewer)
            ->get(route('geoguessr.index', ['tab' => 'graphs']))
            ->assertOk()
            ->assertSee('TodayInsightToken')
            ->assertSee('4321')
            ->assertSee('YesterdayInsightToken')
            ->assertSee('-10.6741219')
            ->assertSee('"country":"PE"', false)
            ->assertSee('"continent":"South America"', false)
            ->assertDontSee('12.3456789')
            ->assertDontSee('98.7654321')
            ->assertDontSee('"country":"JP"', false)
            ->assertDontSee('"continent":"Asia"', false);
    }

    public function test_graphs_include_todays_locations_after_the_viewer_plays(): void
    {
        $viewer = User::factory()->create(['name' => 'Viewer']);
        $today = GeoguesserChallenge::factory()->create([
            'geoguesser_id' => Geoguesser::factory()->create([
                'user_id' => $viewer->id,
                'username' => 'ViewerOnGeo',
            ]),
            'challenge_token' => 'TodayInsightToken',
            'attempted_at' => now(),
            'total_score' => 5000,
        ]);

        GeoguesserRound::factory()->create([
            'geoguesser_challenge_id' => $today->id,
            'round_number' => 1,
            'actual_lat' => 12.3456789,
            'actual_lng' => 98.7654321,
            'guess_lat' => 11.111,
            'guess_lng' => 22.222,
            'score' => 4321,
            'country_code' => 'jp',
        ]);

        $this->actingAs($viewer)
            ->get(route('geoguessr.index', ['tab' => 'graphs']))
            ->assertOk()
            ->assertSee('12.3456789')
            ->assertSee('98.7654321')
            ->assertSee('"country":"JP"', false)
            ->assertSee('"continent":"Asia"', false);
    }

    public function test_challenges_tab_includes_round_locations(): void
    {
        $viewer = User::factory()->create();
        $player = User::factory()->create(['name' => 'Historical Hank']);
        $challenge = GeoguesserChallenge::factory()->create([
            'geoguesser_id' => Geoguesser::factory()->create([
                'user_id' => $player->id,
                'username' => 'HankOnGeo',
            ]),
            'challenge_token' => 'NJTAratoSkpaMgAd',
            'map_name' => 'World',
            'attempted_at' => now()->subDay(),
        ]);

        GeoguesserRound::factory()->create([
            'geoguesser_challenge_id' => $challenge->id,
            'round_number' => 1,
            'actual_lat' => -10.6741219,
            'actual_lng' => -76.7747612,
            'guess_lat' => -7.5501728,
            'guess_lng' => -75.3326236,
            'score' => 3871,
            'percentage' => 77.42,
            'time' => 180,
            'steps_count' => 13,
            'distance_in_meters' => 381742,
            'country_code' => 'pe',
            'pano_id' => 'YesterdayPanoToken',
            'heading' => 120.5,
            'pitch' => -4.2,
        ]);

        $this->actingAs($viewer)
            ->get(route('geoguessr.index', ['tab' => 'challenges']))
            ->assertOk()
            ->assertSee('Challenges')
            ->assertSee('NJTAratoSkpaMgAd')
            ->assertSee('Historical Hank (HankOnGeo)')
            ->assertSee('-10.6741219')
            ->assertSee('3871')
            ->assertSee('381742')
            ->assertSee('HH')
            ->assertSee('YesterdayPanoToken');
    }

    public function test_challenges_summarise_the_day_winner_and_order_round_guesses_by_score(): void
    {
        $viewer = User::factory()->create();
        $token = 'DayWinnerToken';
        $second = GeoguesserChallenge::factory()->create([
            'geoguesser_id' => Geoguesser::factory()->create([
                'user_id' => User::factory()->create(['name' => 'Damien Second']),
            ]),
            'challenge_token' => $token,
            'map_name' => 'World',
            'attempted_at' => now()->subDay(),
            'total_score' => 9432,
        ]);
        $winner = GeoguesserChallenge::factory()->create([
            'geoguesser_id' => Geoguesser::factory()->create([
                'user_id' => User::factory()->create(['name' => 'Celeste Winner']),
            ]),
            'challenge_token' => $token,
            'map_name' => 'World',
            'attempted_at' => now()->subDay(),
            'total_score' => 18765,
        ]);

        GeoguesserRound::factory()->create([
            'geoguesser_challenge_id' => $second->id,
            'round_number' => 1,
            'score' => 1023,
            'percentage' => 20.46,
            'guess_lat' => 1.1,
            'guess_lng' => 2.2,
            'country_code' => 'de',
        ]);
        GeoguesserRound::factory()->create([
            'geoguesser_challenge_id' => $winner->id,
            'round_number' => 1,
            'score' => 4987,
            'percentage' => 99.74,
            'guess_lat' => 3.3,
            'guess_lng' => 4.4,
            'country_code' => 'de',
        ]);

        $this->actingAs($viewer)
            ->get(route('geoguessr.index', ['tab' => 'challenges']))
            ->assertOk()
            ->assertSee('The day')
            ->assertSee('"score":18765,"place":1', false)
            ->assertSee('"score":9432,"place":2', false)
            ->assertSeeInOrder(['"score":4987,"percent"', '"score":1023,"percent"']);
    }

    public function test_todays_challenge_locations_are_hidden_until_the_viewer_plays(): void
    {
        $viewer = User::factory()->create();
        $other = User::factory()->create(['name' => 'Other Player']);
        $geoguesser = Geoguesser::factory()->create([
            'user_id' => $other->id,
            'username' => 'OtherOnGeo',
        ]);
        $today = GeoguesserChallenge::factory()->create([
            'geoguesser_id' => $geoguesser->id,
            'challenge_token' => 'TodayTokenSecret',
            'map_name' => 'World',
            'attempted_at' => now(),
        ]);

        GeoguesserRound::factory()->create([
            'geoguesser_challenge_id' => $today->id,
            'round_number' => 1,
            'actual_lat' => 12.3456789,
            'actual_lng' => 98.7654321,
            'guess_lat' => 11.111,
            'guess_lng' => 22.222,
            'score' => 5000,
            'country_code' => 'fr',
            'pano_id' => 'SecretTodayPanoId',
        ]);

        $yesterday = GeoguesserChallenge::factory()->create([
            'geoguesser_id' => $geoguesser->id,
            'challenge_token' => 'YesterdayToken',
            'map_name' => 'World',
            'attempted_at' => now()->subDay(),
        ]);

        GeoguesserRound::factory()->create([
            'geoguesser_challenge_id' => $yesterday->id,
            'round_number' => 1,
            'actual_lat' => -10.6741219,
            'actual_lng' => -76.7747612,
            'guess_lat' => -7.5501728,
            'guess_lng' => -75.3326236,
            'score' => 3871,
            'country_code' => 'pe',
        ]);

        $this->actingAs($viewer)
            ->get(route('geoguessr.index', ['tab' => 'challenges']))
            ->assertOk()
            ->assertSee(today()->toFormattedDateString())
            ->assertSee('TodayTokenSecret')
            ->assertSee('data-locked="true"', false)
            ->assertSee('YesterdayToken')
            ->assertSee('-10.6741219')
            ->assertDontSee('12.3456789')
            ->assertDontSee('98.7654321')
            ->assertDontSee('SecretTodayPanoId');
    }

    public function test_todays_challenge_locations_are_visible_after_the_viewer_plays(): void
    {
        $viewer = User::factory()->create(['name' => 'Viewer']);
        $today = GeoguesserChallenge::factory()->create([
            'geoguesser_id' => Geoguesser::factory()->create([
                'user_id' => $viewer->id,
                'username' => 'ViewerOnGeo',
            ]),
            'challenge_token' => 'TodayTokenSecret',
            'map_name' => 'World',
            'attempted_at' => now(),
        ]);

        GeoguesserRound::factory()->create([
            'geoguesser_challenge_id' => $today->id,
            'round_number' => 1,
            'actual_lat' => 12.3456789,
            'actual_lng' => 98.7654321,
            'guess_lat' => 11.111,
            'guess_lng' => 22.222,
            'score' => 5000,
            'country_code' => 'fr',
        ]);

        $this->actingAs($viewer)
            ->get(route('geoguessr.index', ['tab' => 'challenges']))
            ->assertOk()
            ->assertSee('12.3456789')
            ->assertSee('98.7654321')
            ->assertSee('data-locked="false"', false)
            ->assertDontSee('data-locked="true"', false);
    }
}
