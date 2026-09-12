<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Push\FcmClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Response as GuzzleResponse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\ConfiguresFirebase;
use Tests\TestCase;

class OutgoingApiLogTest extends TestCase
{
    use ConfiguresFirebase;
    use RefreshDatabase;

    public function test_fcm_sends_are_written_to_the_log_without_bearer_tokens(): void
    {
        $this->enableFirebase();
        Cache::flush();
        Event::fake([MessageLogged::class]);
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

        $sent = app(FcmClient::class)->sendToToken(str_repeat('t', 40), [
            'title' => 'Club day',
            'body' => 'Hello',
            'url' => 'https://example.test/wickets',
        ]);

        $this->assertTrue($sent);
        Event::assertDispatched(MessageLogged::class, function (MessageLogged $event): bool {
            return $event->message === 'Outgoing API request'
                && $event->context['url'] === 'https://oauth2.googleapis.com/token'
                && $event->context['status'] === 200
                && $event->context['request']['assertion'] === '[redacted]'
                && $event->context['response']['access_token'] === '[redacted]';
        });
        Event::assertDispatched(MessageLogged::class, function (MessageLogged $event): bool {
            $encoded = json_encode($event->context);

            return $event->message === 'Outgoing API request'
                && $event->context['url'] === 'https://fcm.googleapis.com/v1/projects/spice-rules-test/messages:send'
                && $event->context['status'] === 200
                && $event->context['request_headers']['Authorization'] === ['[redacted]']
                && is_string($encoded)
                && ! str_contains($encoded, 'ya29.test-token');
        });
    }

    public function test_failed_fcm_sends_are_logged_as_warnings(): void
    {
        $this->enableFirebase();
        Cache::flush();
        Event::fake([MessageLogged::class]);
        Http::preventStrayRequests();
        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response([
                'access_token' => 'ya29.test-token',
                'expires_in' => 3600,
            ]),
            'https://fcm.googleapis.com/v1/projects/spice-rules-test/messages:send' => Http::response([
                'error' => ['message' => 'Internal'],
            ], 500),
        ]);

        $sent = app(FcmClient::class)->sendToToken(str_repeat('t', 40), [
            'title' => 'Club day',
            'body' => 'Hello',
            'url' => 'https://example.test/wickets',
        ]);

        $this->assertFalse($sent);
        Event::assertDispatched(MessageLogged::class, function (MessageLogged $event): bool {
            return $event->message === 'Outgoing API request'
                && $event->level === 'warning'
                && $event->context['url'] === 'https://fcm.googleapis.com/v1/projects/spice-rules-test/messages:send'
                && $event->context['status'] === 500;
        });
    }

    public function test_password_reset_resend_calls_are_written_to_the_log_without_the_api_key_or_reset_link(): void
    {
        Event::fake([MessageLogged::class]);
        $this->app->instance('resend.guzzle.handler', new MockHandler([
            new GuzzleResponse(200, ['Content-Type' => 'application/json'], json_encode([
                'id' => 're_test_email_id',
            ])),
        ]));
        config([
            'mail.default' => 'resend',
            'mail.from.address' => 'hello@example.com',
            'mail.from.name' => 'Spice Rules',
            'services.resend.key' => 're_test_secret_key',
        ]);
        Mail::purge('resend');

        $user = User::factory()->create();

        $this->post(route('password.email'), ['email' => $user->email])
            ->assertRedirect()
            ->assertSessionHas('status');

        Event::assertDispatched(MessageLogged::class, function (MessageLogged $event): bool {
            $encoded = json_encode($event->context);

            return $event->message === 'Outgoing API request'
                && $event->context['url'] === 'https://api.resend.com/emails'
                && $event->context['status'] === 200
                && $event->context['request_headers']['Authorization'] === ['[redacted]']
                && ($event->context['request']['html'] ?? null) === '[redacted]'
                && ($event->context['request']['text'] ?? null) === '[redacted]'
                && $event->context['response']['id'] === 're_test_email_id'
                && is_string($encoded)
                && ! str_contains($encoded, 're_test_secret_key');
        });
    }
}
