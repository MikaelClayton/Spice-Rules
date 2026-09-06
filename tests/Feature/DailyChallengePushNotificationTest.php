<?php

namespace Tests\Feature;

use App\Models\DeviceToken;
use App\Models\Geoguesser;
use App\Models\GeoguesserChallenge;
use App\Models\User;
use Carbon\Carbon;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\ConfiguresFirebase;
use Tests\TestCase;

class DailyChallengePushNotificationTest extends TestCase
{
    use ConfiguresFirebase;
    use RefreshDatabase;

    public function test_it_notifies_other_devices_when_todays_daily_is_first_saved(): void
    {
        $this->enableFirebase();
        Cache::flush();

        $player = User::factory()->create(['name' => 'Alex']);
        $friend = User::factory()->create(['name' => 'Sam']);
        $playerToken = DeviceToken::factory()->create([
            'user_id' => $player->id,
            'token' => str_repeat('p', 40),
        ]);
        $friendToken = DeviceToken::factory()->create([
            'user_id' => $friend->id,
            'token' => str_repeat('f', 40),
        ]);

        Geoguesser::factory()->create([
            'user_id' => $player->id,
            'username' => 'Alex',
            'ncfa' => 'test-ncfa',
            'is_active' => true,
        ]);

        $this->fakeGeoGuessrAndFcm();

        $this->artisan('geoguessr:sync')->assertSuccessful();

        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://oauth2.googleapis.com/token');
        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://fcm.googleapis.com/v1/projects/spice-rules-test/messages:send'
            && $request['message']['token'] === $friendToken->token
            && $request['message']['notification']['title'] === 'Daily GeoGuessr'
            && $request['message']['notification']['body'] === 'Alex just scored 13,801 and is currently in 1st.');
        Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), 'messages:send')
            && $request['message']['token'] === $playerToken->token);
    }

    public function test_the_ping_includes_current_place_on_todays_board(): void
    {
        $this->enableFirebase();
        Cache::flush();

        $player = User::factory()->create(['name' => 'Alex']);
        $friend = User::factory()->create(['name' => 'Sam']);
        DeviceToken::factory()->create([
            'user_id' => $friend->id,
            'token' => str_repeat('f', 40),
        ]);

        Geoguesser::factory()->create([
            'user_id' => $player->id,
            'username' => 'Alex',
            'ncfa' => 'test-ncfa',
            'is_active' => true,
        ]);

        GeoguesserChallenge::factory()->create([
            'geoguesser_id' => Geoguesser::factory()->create([
                'user_id' => User::factory(),
            ]),
            'attempted_at' => now()->startOfWeek(Carbon::SUNDAY)->addDays(4),
            'total_score' => 20000,
        ]);

        $this->fakeGeoGuessrAndFcm();

        $this->artisan('geoguessr:sync')->assertSuccessful();

        Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'messages:send')
            && $request['message']['notification']['body'] === 'Alex just scored 13,801 and is currently in 2nd.');
    }

    public function test_it_does_not_notify_when_todays_score_was_already_saved(): void
    {
        $this->enableFirebase();
        Cache::flush();

        $player = User::factory()->create(['name' => 'Alex']);
        $friend = User::factory()->create();
        DeviceToken::factory()->create([
            'user_id' => $friend->id,
            'token' => str_repeat('f', 40),
        ]);

        $geoguesser = Geoguesser::factory()->create([
            'user_id' => $player->id,
            'ncfa' => 'test-ncfa',
            'is_active' => true,
        ]);
        GeoguesserChallenge::factory()->create([
            'geoguesser_id' => $geoguesser->id,
            'challenge_token' => 'NJTAratoSkpaMgAd',
            'total_score' => 13801,
        ]);

        $this->fakeGeoGuessrAndFcm();

        $this->artisan('geoguessr:sync')->assertSuccessful();

        Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), 'messages:send')
            || $request->url() === 'https://oauth2.googleapis.com/token');
    }

    public function test_it_forgets_unregistered_device_tokens(): void
    {
        $this->enableFirebase();
        Cache::flush();

        $player = User::factory()->create(['name' => 'Alex']);
        $friend = User::factory()->create();
        $token = DeviceToken::factory()->create([
            'user_id' => $friend->id,
            'token' => str_repeat('f', 40),
        ]);

        Geoguesser::factory()->create([
            'user_id' => $player->id,
            'ncfa' => 'test-ncfa',
            'is_active' => true,
        ]);

        Http::preventStrayRequests();
        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response([
                'access_token' => 'ya29.test-token',
                'expires_in' => 3600,
            ]),
            'https://fcm.googleapis.com/v1/projects/spice-rules-test/messages:send' => Http::response([
                'error' => [
                    'code' => 404,
                    'status' => 'NOT_FOUND',
                    'details' => [
                        ['errorCode' => 'UNREGISTERED'],
                    ],
                ],
            ], 404),
            ...$this->geoGuessrFake(),
        ]);

        $this->artisan('geoguessr:sync')->assertSuccessful();

        $this->assertModelMissing($token);
    }

    private function fakeGeoGuessrAndFcm(): void
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
            ...$this->geoGuessrFake(),
        ]);
    }

    /**
     * @return array<string, PromiseInterface>
     */
    private function geoGuessrFake(): array
    {
        return [
            'https://www.geoguessr.com/api/v3/profiles' => Http::response([
                'user' => [
                    'nick' => 'Alex',
                    'progress' => [
                        'xp' => 5763,
                        'level' => 18,
                    ],
                ],
            ]),
            'https://www.geoguessr.com/api/v3/challenges/daily-challenges/me/week' => Http::response([
                [
                    'dayOfWeek' => 4,
                    'challengeToken' => 'NJTAratoSkpaMgAd',
                    'isToday' => true,
                    'gameResult' => [
                        'id' => 'today-guid',
                        'totalScore' => 13801,
                        'totalDistance' => 2000,
                        'totalStepsCount' => 20,
                    ],
                ],
            ]),
            'https://www.geoguessr.com/api/v3/profiles/stats' => Http::response([
                'dailyChallengeStreak' => 1,
                'dailyChallengeCurrentStreak' => 1,
                'dailyChallengesRolling7Days' => [],
            ]),
            'https://www.geoguessr.com/api/v3/challenges/NJTAratoSkpaMgAd' => Http::response([
                'token' => 'game-token',
                'type' => 'challenge',
                'state' => 'finished',
                'mapName' => 'World',
                'roundCount' => 1,
                'rounds' => [
                    [
                        'lat' => 1.0,
                        'lng' => 2.0,
                        'heading' => 0,
                        'pitch' => 0,
                        'zoom' => 0,
                        'panoId' => 'abc',
                        'streakLocationCode' => 'za',
                        'startTime' => '2026-09-06T11:08:44.3200000Z',
                    ],
                ],
                'player' => [
                    'totalScore' => ['amount' => '13801'],
                    'totalDistanceInMeters' => 2000,
                    'totalStepsCount' => 20,
                    'guesses' => [
                        [
                            'lat' => 1.1,
                            'lng' => 2.1,
                            'timedOut' => false,
                            'timedOutWithGuess' => false,
                            'skippedRound' => false,
                            'roundScoreInPoints' => 13801,
                            'roundScoreInPercentage' => 100,
                            'distanceInMeters' => 2000,
                            'stepsCount' => 20,
                            'time' => 90,
                        ],
                    ],
                ],
            ]),
        ];
    }
}
