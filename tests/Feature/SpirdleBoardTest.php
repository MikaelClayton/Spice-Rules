<?php

namespace Tests\Feature;

use App\Models\SpirdlePlay;
use App\Models\SpirdlePuzzle;
use App\Models\SpirdleWord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SpirdleBoardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_from_the_board(): void
    {
        $this->get(route('spirdle.index'))
            ->assertRedirect(route('login'));
    }

    public function test_today_offers_play_now_until_the_viewer_finishes(): void
    {
        $this->travelTo('2026-09-16 12:00:00');

        $viewer = User::factory()->create();
        $other = User::factory()->create(['name' => 'Ada']);
        $puzzle = $this->todaysPuzzle('crane');

        SpirdlePlay::factory()->finished(3, true, 9400, 1)->create([
            'user_id' => $other->id,
            'spirdle_puzzle_id' => $puzzle->id,
        ]);

        $this->actingAs($viewer)
            ->get(route('spirdle.index'))
            ->assertOk()
            ->assertSee('Spirdle')
            ->assertSee('Play now')
            ->assertSeeInOrder(['Today', 'Weekly', 'Challenges', 'You'])
            ->assertSee('Ada')
            ->assertSee('3 guesses')
            ->assertSee('9s')
            ->assertSee('1 invalid')
            ->assertDontSee('crane')
            ->assertDontSee('See how Ada played')
            ->assertDontSee(route('spirdle.plays.show', SpirdlePlay::query()->first()), false)
            ->assertSee('data-poll-url="'.e(route('spirdle.live', absolute: false)).'"', false)
            ->assertSee('data-live-poll', false)
            ->assertSee('data-live-region="today"', false);
    }

    public function test_today_shows_mini_grids_after_the_viewer_finishes(): void
    {
        $this->travelTo('2026-09-16 12:00:00');

        $viewer = User::factory()->create(['name' => 'Ben']);
        $puzzle = $this->todaysPuzzle('crane');

        $play = SpirdlePlay::factory()->finished(2, true, 4100)->create([
            'user_id' => $viewer->id,
            'spirdle_puzzle_id' => $puzzle->id,
        ]);

        $this->actingAs($viewer)
            ->get(route('spirdle.index'))
            ->assertOk()
            ->assertDontSee('Play now')
            ->assertSee('Ben')
            ->assertSee('bg-success', false)
            ->assertSee('See how Ben played')
            ->assertSee(route('spirdle.plays.show', $play), false)
            ->assertSee('You')
            ->assertSee('Fastest win');
    }

    public function test_weekly_tab_shows_standings(): void
    {
        $this->travelTo('2026-09-16 12:00:00');

        $user = User::factory()->create(['name' => 'Ada']);
        $puzzle = $this->todaysPuzzle('crane');

        SpirdlePlay::factory()->finished(2, true, 8000)->create([
            'user_id' => $user->id,
            'spirdle_puzzle_id' => $puzzle->id,
        ]);

        $this->actingAs($user)
            ->get(route('spirdle.index', ['tab' => 'weekly']))
            ->assertOk()
            ->assertSee('Standings')
            ->assertSee('Ada')
            ->assertSee('pts');
    }

    public function test_challenges_hide_days_the_viewer_has_not_finished(): void
    {
        $this->travelTo('2026-09-16 12:00:00');

        $viewer = User::factory()->create();
        $other = User::factory()->create(['name' => 'Ada']);
        $today = $this->todaysPuzzle('crane');
        $past = SpirdlePuzzle::factory()->create([
            'spirdle_word_id' => SpirdleWord::factory()->create(['word' => 'about']),
            'play_date' => '2026-09-15',
        ]);

        SpirdlePlay::factory()->finished(3, true, 8000, 2)->create([
            'user_id' => $other->id,
            'spirdle_puzzle_id' => $today->id,
        ]);
        SpirdlePlay::factory()->finished(4, true, 12000)->create([
            'user_id' => $other->id,
            'spirdle_puzzle_id' => $past->id,
        ]);

        $this->actingAs($viewer)
            ->get(route('spirdle.index', ['tab' => 'challenges']))
            ->assertOk()
            ->assertSee('Finish a daily Spirdle to unlock that recap.')
            ->assertDontSee('crane')
            ->assertDontSee('about')
            ->assertDontSee('data-challenge-token="2026-09-16"', false)
            ->assertDontSee('data-challenge-token="2026-09-15"', false);
    }

    public function test_challenges_show_attempts_misses_time_and_the_word_after_a_finish(): void
    {
        $this->travelTo('2026-09-16 12:00:00');

        $viewer = User::factory()->create(['name' => 'Ben']);
        $puzzle = $this->todaysPuzzle('crane');

        SpirdlePlay::factory()->finished(2, true, 5400, 1)->create([
            'user_id' => $viewer->id,
            'spirdle_puzzle_id' => $puzzle->id,
        ]);

        $this->actingAs($viewer)
            ->get(route('spirdle.index', ['tab' => 'challenges']))
            ->assertOk()
            ->assertSee('crane')
            ->assertSee('data-locked="false"', false)
            ->assertSee('"attempts":2', false)
            ->assertSee('"missed":1', false)
            ->assertSee('"time":"5s"', false)
            ->assertSee('See how Ben played')
            ->assertSee('tab=challenges', false)
            ->assertSee('challenge=2026-09-16', false)
            ->assertDontSee('The day')
            ->assertSee('bg-success', false);
    }

    private function todaysPuzzle(string $word): SpirdlePuzzle
    {
        return SpirdlePuzzle::factory()->create([
            'spirdle_word_id' => SpirdleWord::factory()->create(['word' => $word]),
            'play_date' => today()->toDateString(),
        ]);
    }
}
