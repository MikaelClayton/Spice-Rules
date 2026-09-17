<?php

namespace Tests\Feature;

use App\Models\SpirdlePlay;
use App\Models\SpirdlePuzzle;
use App\Models\SpirdleWord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SpirdlePlayTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_open_the_board(): void
    {
        $this->get(route('spirdle.play'))
            ->assertRedirect(route('login'));
    }

    public function test_play_page_starts_a_timer_without_leaking_the_answer(): void
    {
        $this->travelTo('2026-09-16 12:00:00');

        $user = User::factory()->create();
        $this->seedToday('crane');

        $this->actingAs($user)
            ->get(route('spirdle.play'))
            ->assertOk()
            ->assertSee('Spirdle')
            ->assertSee('data-spirdle-play', false)
            ->assertDontSee('crane')
            ->assertDontSee('dxSupportWidget', false)
            ->assertDontSee('https://web.divblox.app/widgets/dxSupportWidget.js', false);

        $this->assertDatabaseHas('spirdle_plays', [
            'user_id' => $user->id,
            'guess_count' => 0,
            'finished_at' => null,
        ]);
    }

    public function test_a_valid_guess_returns_tile_colours(): void
    {
        $this->travelTo('2026-09-16 12:00:00');

        $user = User::factory()->create();
        $this->seedToday('crane');
        $this->actingAs($user)->get(route('spirdle.play'))->assertOk();

        $this->actingAs($user)
            ->postJson(route('spirdle.guesses.store'), ['word' => 'trace'])
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('play.guesses.0.word', 'trace')
            ->assertJsonPath('play.guesses.0.tiles', ['absent', 'correct', 'correct', 'present', 'correct'])
            ->assertJsonPath('play.finished', false)
            ->assertJsonPath('play.solution', null);
    }

    public function test_solving_the_word_stores_guesses_time_and_invalids(): void
    {
        $this->travelTo('2026-09-16 12:00:00');

        $user = User::factory()->create();
        $this->seedToday('crane');
        $this->actingAs($user)->get(route('spirdle.play'))->assertOk();

        $this->actingAs($user)
            ->postJson(route('spirdle.guesses.store'), ['word' => 'zzzzz'])
            ->assertOk()
            ->assertJsonPath('status', 'invalid')
            ->assertJsonPath('play.invalidWordCount', 1);

        $this->travel(8)->seconds();

        $this->actingAs($user)
            ->postJson(route('spirdle.guesses.store'), ['word' => 'crane'])
            ->assertOk()
            ->assertJsonPath('status', 'finished')
            ->assertJsonPath('play.won', true)
            ->assertJsonPath('play.guessCount', 1)
            ->assertJsonPath('play.invalidWordCount', 1)
            ->assertJsonPath('play.solution', 'crane');

        $play = SpirdlePlay::query()->whereBelongsTo($user)->first();

        $this->assertNotNull($play?->finished_at);
        $this->assertSame(1, $play->guess_count);
        $this->assertSame(1, $play->invalid_word_count);
        $this->assertTrue($play->won);
        $this->assertSame(8000, $play->duration_ms);
    }

    public function test_leaving_the_board_pauses_the_timer(): void
    {
        $this->travelTo('2026-09-16 12:00:00');

        $user = User::factory()->create();
        $this->seedToday('crane');
        $this->actingAs($user)->get(route('spirdle.play'))->assertOk();

        $this->travel(5)->seconds();

        $this->actingAs($user)
            ->postJson(route('spirdle.pause'))
            ->assertOk()
            ->assertJsonPath('play.paused', true);

        $this->travel(20)->seconds();

        $this->actingAs($user)
            ->postJson(route('spirdle.resume'))
            ->assertOk()
            ->assertJsonPath('play.paused', false);

        $this->travel(3)->seconds();

        $this->actingAs($user)
            ->postJson(route('spirdle.guesses.store'), ['word' => 'crane'])
            ->assertOk()
            ->assertJsonPath('play.won', true);

        $play = SpirdlePlay::query()->whereBelongsTo($user)->first();

        $this->assertSame(8000, $play?->duration_ms);
        $this->assertNull($play->running_since);
    }

    public function test_opening_play_again_resumes_a_paused_timer(): void
    {
        $this->travelTo('2026-09-16 12:00:00');

        $user = User::factory()->create();
        $this->seedToday('crane');
        $this->actingAs($user)->get(route('spirdle.play'))->assertOk();
        $this->actingAs($user)->postJson(route('spirdle.pause'))->assertOk();

        $this->travel(12)->seconds();

        $this->actingAs($user)
            ->get(route('spirdle.play'))
            ->assertOk();

        $play = SpirdlePlay::query()->whereBelongsTo($user)->first();

        $this->assertNotNull($play?->running_since);
        $this->assertFalse($play->isPaused());
    }

    public function test_guests_cannot_pause_the_timer(): void
    {
        $this->postJson(route('spirdle.pause'))
            ->assertUnauthorized();
    }

    public function test_six_misses_finish_the_play_as_a_loss(): void
    {
        $this->travelTo('2026-09-16 12:00:00');

        $user = User::factory()->create();
        $this->seedToday('crane');
        $this->actingAs($user)->get(route('spirdle.play'))->assertOk();

        foreach (['about', 'world', 'music', 'table', 'house'] as $word) {
            $this->actingAs($user)
                ->postJson(route('spirdle.guesses.store'), ['word' => $word])
                ->assertOk()
                ->assertJsonPath('play.finished', false);
        }

        $this->actingAs($user)
            ->postJson(route('spirdle.guesses.store'), ['word' => 'plain'])
            ->assertOk()
            ->assertJsonPath('status', 'finished')
            ->assertJsonPath('play.won', false)
            ->assertJsonPath('play.guessCount', 6)
            ->assertJsonPath('play.solution', 'crane');
    }

    public function test_repeat_guesses_are_rejected_without_using_a_turn(): void
    {
        $this->travelTo('2026-09-16 12:00:00');

        $user = User::factory()->create();
        $this->seedToday('crane');
        $this->actingAs($user)->get(route('spirdle.play'))->assertOk();

        $this->actingAs($user)
            ->postJson(route('spirdle.guesses.store'), ['word' => 'about'])
            ->assertOk();

        $this->actingAs($user)
            ->postJson(route('spirdle.guesses.store'), ['word' => 'about'])
            ->assertOk()
            ->assertJsonPath('status', 'repeat')
            ->assertJsonPath('play.guessCount', 1);
    }

    public function test_short_guesses_return_a_validation_message(): void
    {
        $this->travelTo('2026-09-16 12:00:00');

        $user = User::factory()->create();
        $this->seedToday('crane');
        $this->actingAs($user)->get(route('spirdle.play'))->assertOk();

        $this->actingAs($user)
            ->postJson(route('spirdle.guesses.store'), ['word' => 'hi'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.word.0', 'Use 5 letters.');
    }

    public function test_finished_players_can_review_a_board_without_the_keyboard(): void
    {
        $this->travelTo('2026-09-16 12:00:00');

        $viewer = User::factory()->create();
        $other = User::factory()->create(['name' => 'Ada']);
        $puzzle = $this->seedToday('crane');

        SpirdlePlay::factory()->finished(1, true, 4000)->create([
            'user_id' => $viewer->id,
            'spirdle_puzzle_id' => $puzzle->id,
        ]);
        $play = SpirdlePlay::factory()->finished(3, true, 12000)->create([
            'user_id' => $other->id,
            'spirdle_puzzle_id' => $puzzle->id,
        ]);

        $this->actingAs($viewer)
            ->get(route('spirdle.plays.show', $play))
            ->assertOk()
            ->assertSee('Ada')
            ->assertSee('try00')
            ->assertSee('data-readonly="true"', false)
            ->assertDontSee('data-key=', false)
            ->assertDontSee('data-guess-url', false);
    }

    public function test_review_from_challenges_returns_to_that_day(): void
    {
        $this->travelTo('2026-09-16 12:00:00');

        $viewer = User::factory()->create();
        $puzzle = $this->seedToday('crane');
        $play = SpirdlePlay::factory()->finished(1, true, 4000)->create([
            'user_id' => $viewer->id,
            'spirdle_puzzle_id' => $puzzle->id,
        ]);

        $this->actingAs($viewer)
            ->get(route('spirdle.plays.show', [
                'spirdlePlay' => $play,
                'tab' => 'challenges',
                'challenge' => '2026-09-16',
            ]))
            ->assertOk()
            ->assertSee('href="'.e(route('spirdle.index', [
                'tab' => 'challenges',
                'challenge' => '2026-09-16',
            ])).'"', false);
    }

    public function test_review_from_today_returns_to_today(): void
    {
        $this->travelTo('2026-09-16 12:00:00');

        $viewer = User::factory()->create();
        $puzzle = $this->seedToday('crane');
        $play = SpirdlePlay::factory()->finished(1, true, 4000)->create([
            'user_id' => $viewer->id,
            'spirdle_puzzle_id' => $puzzle->id,
        ]);

        $this->actingAs($viewer)
            ->get(route('spirdle.plays.show', $play))
            ->assertOk()
            ->assertSee('href="'.e(route('spirdle.index')).'"', false)
            ->assertDontSee('tab=challenges', false);
    }

    public function test_a_play_cannot_be_reviewed_until_the_viewer_finishes_that_daily(): void
    {
        $this->travelTo('2026-09-16 12:00:00');

        $viewer = User::factory()->create();
        $other = User::factory()->create(['name' => 'Ada']);
        $puzzle = $this->seedToday('crane');

        $play = SpirdlePlay::factory()->finished(3, true, 12000)->create([
            'user_id' => $other->id,
            'spirdle_puzzle_id' => $puzzle->id,
        ]);

        $this->actingAs($viewer)
            ->get(route('spirdle.plays.show', $play))
            ->assertRedirect(route('spirdle.index'));
    }

    public function test_unfinished_plays_cannot_be_reviewed(): void
    {
        $this->travelTo('2026-09-16 12:00:00');

        $viewer = User::factory()->create();
        $other = User::factory()->create();
        $puzzle = $this->seedToday('crane');

        SpirdlePlay::factory()->finished(1, true, 4000)->create([
            'user_id' => $viewer->id,
            'spirdle_puzzle_id' => $puzzle->id,
        ]);
        $play = SpirdlePlay::factory()->create([
            'user_id' => $other->id,
            'spirdle_puzzle_id' => $puzzle->id,
            'guesses' => [['word' => 'about', 'tiles' => ['absent', 'absent', 'absent', 'absent', 'absent']]],
        ]);

        $this->actingAs($viewer)
            ->get(route('spirdle.plays.show', $play))
            ->assertNotFound();
    }

    public function test_guests_cannot_review_a_play(): void
    {
        $play = SpirdlePlay::factory()->finished()->create();

        $this->get(route('spirdle.plays.show', $play))
            ->assertRedirect(route('login'));
    }

    public function test_a_guess_before_opening_the_board_returns_409(): void
    {
        $this->travelTo('2026-09-16 12:00:00');

        $user = User::factory()->create();
        $this->seedToday('crane');

        $this->actingAs($user)
            ->postJson(route('spirdle.guesses.store'), ['word' => 'crane'])
            ->assertConflict();
    }

    private function seedToday(string $answer): SpirdlePuzzle
    {
        $answerWord = SpirdleWord::factory()->create(['word' => $answer, 'is_answer' => true]);

        foreach (['about', 'world', 'music', 'table', 'house', 'plain', 'trace'] as $word) {
            SpirdleWord::factory()->guessOnly()->create(['word' => $word]);
        }

        return SpirdlePuzzle::factory()->create([
            'spirdle_word_id' => $answerWord->id,
            'play_date' => today()->toDateString(),
        ]);
    }
}
