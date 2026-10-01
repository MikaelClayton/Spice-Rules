<?php

namespace Tests\Feature;

use App\Models\DeviceToken;
use App\Models\SpirdlePlay;
use App\Models\SpirdlePractice;
use App\Models\SpirdlePuzzle;
use App\Models\SpirdleWord;
use App\Models\User;
use App\Services\Spirdle\BuildSpirdlePracticeStats;
use App\Services\Spirdle\BuildSpirdleYou;
use App\Services\Spirdle\EnsureTodaysSpirdlePuzzle;
use App\Services\Spirdle\StartSpirdlePractice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\ConfiguresFirebase;
use Tests\TestCase;

class SpirdlePracticeTest extends TestCase
{
    use ConfiguresFirebase;
    use RefreshDatabase;

    public function test_guests_cannot_open_practice(): void
    {
        $this->get(route('spirdle.practice'))
            ->assertRedirect(route('login'));
    }

    public function test_practice_uses_a_word_that_is_not_todays_daily(): void
    {
        $this->travelTo('2026-09-16 12:00:00');

        $user = User::factory()->create();
        $this->seedToday('crane', ['about', 'world']);

        $this->actingAs($user)
            ->get(route('spirdle.practice'))
            ->assertOk()
            ->assertSee('Practice')
            ->assertSee('data-replay-url', false)
            ->assertSee(route('spirdle.practice.guesses.store', absolute: false), false)
            ->assertDontSee('crane')
            ->assertDontSee('data-guess-url="'.e(route('spirdle.guesses.store')).'"', false);

        $practice = SpirdlePractice::query()->whereBelongsTo($user)->first();

        $this->assertNotNull($practice);
        $this->assertNotSame('crane', $practice->roundSolution());
        $this->assertTrue(in_array($practice->roundSolution(), ['about', 'world'], true));
        $this->assertSame(0, SpirdlePlay::query()->count());
    }

    public function test_practice_continues_an_unfinished_round(): void
    {
        $this->travelTo('2026-09-16 12:00:00');

        $user = User::factory()->create();
        $this->seedToday('crane', ['about']);

        $this->actingAs($user)->get(route('spirdle.practice'))->assertOk();
        $this->actingAs($user)
            ->postJson(route('spirdle.practice.guesses.store'), ['word' => 'music'])
            ->assertOk()
            ->assertJsonPath('status', 'ok');

        $this->actingAs($user)->get(route('spirdle.practice'))->assertOk();

        $this->assertSame(1, SpirdlePractice::query()->whereBelongsTo($user)->count());
        $this->assertSame(1, SpirdlePractice::query()->whereBelongsTo($user)->whereNull('finished_at')->count());
    }

    public function test_finishing_practice_counts_separately_from_daily_stats(): void
    {
        $this->travelTo('2026-09-16 12:00:00');

        $user = User::factory()->create(['name' => 'Ben']);
        $puzzle = $this->seedToday('crane', ['about']);

        $this->actingAs($user)->get(route('spirdle.practice'))->assertOk();
        $this->actingAs($user)
            ->postJson(route('spirdle.practice.guesses.store'), ['word' => 'about'])
            ->assertOk()
            ->assertJsonPath('status', 'finished')
            ->assertJsonPath('play.won', true)
            ->assertJsonPath('play.solution', 'about');

        $this->assertSame(1, SpirdlePractice::query()->finished()->count());
        $this->assertSame(0, SpirdlePlay::query()->count());

        $you = app(BuildSpirdleYou::class)->handle($user);
        $this->assertFalse($you['hasData']);
        $this->assertSame(0, $you['stats']['played']);

        $this->actingAs($user)
            ->get(route('spirdle.index'))
            ->assertOk()
            ->assertSee('1 practice finished')
            ->assertSee('Play now')
            ->assertDontSee('See how Ben played');

        $this->actingAs($user)
            ->get(route('spirdle.index', ['tab' => 'you']))
            ->assertOk()
            ->assertSee('Warm-ups stay off the daily board')
            ->assertSee('Avg time')
            ->assertSee('Missed 2×+')
            ->assertSee('Words you struggle with')
            ->assertSee('>1</dd>', false)
            ->assertSee('data-practice-results-url="'.e(route('spirdle.practice.results', absolute: false)).'"', false);

        $this->actingAs($user)
            ->getJson(route('spirdle.practice.results'))
            ->assertOk()
            ->assertJsonPath('html', fn ($html) => is_string($html) && str_contains($html, 'bg-success') && str_contains($html, 'See practice from'));

        $practice = SpirdlePractice::query()->whereBelongsTo($user)->finished()->first();
        $this->assertNotNull($practice);

        $this->actingAs($user)
            ->get(route('spirdle.practices.show', $practice))
            ->assertOk()
            ->assertSee('data-readonly="true"', false)
            ->assertSee('ABOUT');

        SpirdlePlay::factory()->finished(2, true, 8000)->create([
            'user_id' => $user->id,
            'spirdle_puzzle_id' => $puzzle->id,
        ]);

        $you = app(BuildSpirdleYou::class)->handle($user->fresh());
        $this->assertSame(1, $you['stats']['played']);
        $this->assertSame(1, $you['stats']['wins']);

        $stats = app(BuildSpirdlePracticeStats::class)->handle($user);
        $this->assertSame(1, $stats['stats']['played']);
        $this->assertNotNull($stats['stats']['averageDurationLabel']);
    }

    public function test_practice_results_are_private(): void
    {
        $this->travelTo('2026-09-16 12:00:00');

        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $this->seedToday('crane', ['about']);

        $practice = SpirdlePractice::factory()->finished(2, true, 5400)->create([
            'user_id' => $owner->id,
            'spirdle_word_id' => SpirdleWord::query()->where('word', 'about')->value('id'),
        ]);

        $this->actingAs($stranger)
            ->get(route('spirdle.practices.show', $practice))
            ->assertNotFound();

        $this->actingAs($stranger)
            ->getJson(route('spirdle.practice.results'))
            ->assertOk()
            ->assertJsonPath('html', fn ($html) => is_string($html) && str_contains($html, 'Finish a practice round'));
    }

    public function test_practice_does_not_notify_devices(): void
    {
        $this->travelTo('2026-09-16 12:00:00');
        $this->enableFirebase();
        Http::preventStrayRequests();
        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response([
                'access_token' => 'ya29.test-token',
                'expires_in' => 3600,
            ]),
            'https://fcm.googleapis.com/v1/projects/spice-rules-test/messages:send' => Http::response([
                'name' => 'projects/spice-rules-test/messages/1',
            ]),
        ]);

        $player = User::factory()->create();
        $friend = User::factory()->create();
        DeviceToken::factory()->create([
            'user_id' => $friend->id,
            'token' => str_repeat('f', 40),
        ]);

        $puzzle = $this->seedToday('crane', ['about']);
        SpirdlePlay::factory()->finished(3, true, 9000)->create([
            'user_id' => $friend->id,
            'spirdle_puzzle_id' => $puzzle->id,
        ]);

        $this->actingAs($player)->get(route('spirdle.practice'))->assertOk();
        $this->actingAs($player)
            ->postJson(route('spirdle.practice.guesses.store'), ['word' => 'about'])
            ->assertOk()
            ->assertJsonPath('status', 'finished');

        Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), 'messages:send'));
    }

    public function test_a_guess_before_opening_practice_returns_409(): void
    {
        $this->travelTo('2026-09-16 12:00:00');

        $user = User::factory()->create();
        $this->seedToday('crane', ['about']);

        $this->actingAs($user)
            ->postJson(route('spirdle.practice.guesses.store'), ['word' => 'about'])
            ->assertConflict();
    }

    public function test_practice_can_guess_a_proper_noun_but_never_uses_one_as_the_answer(): void
    {
        $this->travelTo('2026-09-16 12:00:00');

        $user = User::factory()->create();
        $this->seedToday('crane', ['about']);
        SpirdleWord::factory()->create(['word' => 'james', 'is_answer' => false]);
        SpirdleWord::factory()->create(['word' => 'paris', 'is_answer' => false]);

        for ($attempt = 0; $attempt < 8; $attempt++) {
            $practice = app(StartSpirdlePractice::class)->handle($user);
            $this->assertNotNull($practice);
            $this->assertNotSame('james', $practice->roundSolution());
            $this->assertNotSame('paris', $practice->roundSolution());
            $practice->delete();
        }

        $this->actingAs($user)->get(route('spirdle.practice'))->assertOk();
        $this->actingAs($user)
            ->postJson(route('spirdle.practice.guesses.store'), ['word' => 'james'])
            ->assertOk()
            ->assertJsonPath('status', 'ok');
    }

    public function test_daily_never_opens_on_a_proper_noun(): void
    {
        $this->travelTo('2026-09-16 12:00:00');

        SpirdleWord::factory()->create(['word' => 'james', 'is_answer' => false]);
        SpirdleWord::factory()->create(['word' => 'crane', 'is_answer' => true]);

        $puzzle = app(EnsureTodaysSpirdlePuzzle::class)->handle();

        $this->assertNotNull($puzzle);
        $this->assertSame('crane', $puzzle->solution());
    }

    /**
     * @param  list<string>  $extraAnswers
     */
    private function seedToday(string $answer, array $extraAnswers = []): SpirdlePuzzle
    {
        $answerWord = SpirdleWord::factory()->create(['word' => $answer, 'is_answer' => true]);

        foreach (['music', 'table', 'house', 'plain', 'trace'] as $word) {
            SpirdleWord::factory()->guessOnly()->create(['word' => $word]);
        }

        foreach ($extraAnswers as $word) {
            SpirdleWord::factory()->create(['word' => $word, 'is_answer' => true]);
        }

        return SpirdlePuzzle::factory()->create([
            'spirdle_word_id' => $answerWord->id,
            'play_date' => today()->toDateString(),
        ]);
    }
}
