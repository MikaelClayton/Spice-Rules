<?php

namespace Tests\Feature;

use App\Models\DeviceToken;
use App\Models\SpirdlePlay;
use App\Models\SpirdlePuzzle;
use App\Models\SpirdleWord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\ConfiguresFirebase;
use Tests\TestCase;

class SpirdleFinishPushNotificationTest extends TestCase
{
    use ConfiguresFirebase;
    use RefreshDatabase;

    public function test_solving_notifies_people_who_have_played_spirdle_before(): void
    {
        $this->travelTo('2026-09-16 12:00:00');
        $this->enableFirebase();
        $this->fakeFcm();

        $player = User::factory()->create(['name' => 'Alex']);
        $friend = User::factory()->create(['name' => 'Sam']);
        $stranger = User::factory()->create();
        $playerToken = DeviceToken::factory()->create([
            'user_id' => $player->id,
            'token' => str_repeat('p', 40),
        ]);
        $friendToken = DeviceToken::factory()->create([
            'user_id' => $friend->id,
            'token' => str_repeat('f', 40),
        ]);
        $strangerToken = DeviceToken::factory()->create([
            'user_id' => $stranger->id,
            'token' => str_repeat('s', 40),
        ]);

        $puzzle = $this->seedToday('crane');
        SpirdlePlay::factory()->finished(4, true, 20000)->create([
            'user_id' => $friend->id,
            'spirdle_puzzle_id' => $puzzle->id,
        ]);

        $this->actingAs($player)->get(route('spirdle.play'))->assertOk();
        $this->actingAs($player)
            ->postJson(route('spirdle.guesses.store'), ['word' => 'crane'])
            ->assertOk()
            ->assertJsonPath('status', 'finished');

        Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'messages:send')
            && $request['message']['token'] === $friendToken->token
            && $request['message']['notification']['title'] === 'Spirdle'
            && $request['message']['notification']['body'] === 'Alex just solved today\'s Spirdle in 1 guess and is currently in 1st.');
        Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), 'messages:send')
            && $request['message']['token'] === $playerToken->token);
        Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), 'messages:send')
            && $request['message']['token'] === $strangerToken->token);
    }

    public function test_missing_the_word_still_notifies_past_players(): void
    {
        $this->travelTo('2026-09-16 12:00:00');
        $this->enableFirebase();
        $this->fakeFcm();

        $player = User::factory()->create(['name' => 'Alex']);
        $friend = User::factory()->create();
        $friendToken = DeviceToken::factory()->create([
            'user_id' => $friend->id,
            'token' => str_repeat('f', 40),
        ]);

        $this->seedToday('crane');
        $past = SpirdlePuzzle::factory()->create([
            'spirdle_word_id' => SpirdleWord::factory()->create(['word' => 'zesty']),
            'play_date' => '2026-09-15',
        ]);
        SpirdlePlay::factory()->finished(3, true, 8000)->create([
            'user_id' => $friend->id,
            'spirdle_puzzle_id' => $past->id,
        ]);
        $this->actingAs($player)->get(route('spirdle.play'))->assertOk();

        foreach (['about', 'world', 'music', 'table', 'house'] as $word) {
            $this->actingAs($player)
                ->postJson(route('spirdle.guesses.store'), ['word' => $word])
                ->assertOk();
        }

        Http::assertNothingSent();

        $this->actingAs($player)
            ->postJson(route('spirdle.guesses.store'), ['word' => 'plain'])
            ->assertOk()
            ->assertJsonPath('play.won', false);

        Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'messages:send')
            && $request['message']['token'] === $friendToken->token
            && $request['message']['notification']['body'] === 'Alex just missed today\'s Spirdle and is currently in 1st.');
    }

    public function test_it_does_not_notify_when_the_play_is_already_finished(): void
    {
        $this->travelTo('2026-09-16 12:00:00');
        $this->enableFirebase();
        $this->fakeFcm();

        $player = User::factory()->create(['name' => 'Alex']);
        $friend = User::factory()->create();
        DeviceToken::factory()->create([
            'user_id' => $friend->id,
            'token' => str_repeat('f', 40),
        ]);

        $puzzle = $this->seedToday('crane');
        SpirdlePlay::factory()->finished(2, true, 5000)->create([
            'user_id' => $friend->id,
            'spirdle_puzzle_id' => $puzzle->id,
        ]);
        $this->actingAs($player)->get(route('spirdle.play'))->assertOk();
        $this->actingAs($player)->postJson(route('spirdle.guesses.store'), ['word' => 'crane'])->assertOk();

        $this->fakeFcm();

        $this->actingAs($player)
            ->postJson(route('spirdle.guesses.store'), ['word' => 'crane'])
            ->assertOk()
            ->assertJsonPath('status', 'finished');

        Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), 'messages:send'));
    }

    private function fakeFcm(): void
    {
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
