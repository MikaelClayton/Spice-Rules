<?php

namespace Tests\Unit\Services\Http;

use App\Services\Http\LogOutgoingApiCall;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class LogOutgoingApiCallTest extends TestCase
{
    public function test_authorization_and_oauth_secrets_are_redacted(): void
    {
        Event::fake([MessageLogged::class]);

        app(LogOutgoingApiCall::class)->record(
            method: 'POST',
            url: 'https://oauth2.googleapis.com/token',
            status: 200,
            requestHeaders: ['Authorization' => ['Bearer secret-token']],
            requestBody: 'grant_type=urn%3Aietf%3Aparams%3Aoauth%3Agrant-type%3Ajwt-bearer&assertion=signed.jwt.value',
            responseHeaders: [],
            responseBody: '{"access_token":"ya29.secret","expires_in":3600}',
        );

        Event::assertDispatched(MessageLogged::class, function (MessageLogged $event): bool {
            $encoded = json_encode($event->context);

            return $event->message === 'Outgoing API request'
                && $event->level === 'info'
                && $event->context['request_headers']['Authorization'] === ['[redacted]']
                && $event->context['request']['assertion'] === '[redacted]'
                && $event->context['response']['access_token'] === '[redacted]'
                && is_string($encoded)
                && ! str_contains($encoded, 'secret-token')
                && ! str_contains($encoded, 'signed.jwt.value')
                && ! str_contains($encoded, 'ya29.secret');
        });
    }

    public function test_resend_email_bodies_are_redacted(): void
    {
        Event::fake([MessageLogged::class]);

        app(LogOutgoingApiCall::class)->record(
            method: 'POST',
            url: 'https://api.resend.com/emails',
            status: 200,
            requestHeaders: ['Authorization' => ['Bearer re_live_secret']],
            requestBody: json_encode([
                'from' => 'hello@example.com',
                'to' => ['user@example.com'],
                'subject' => 'Reset Password Notification',
                'html' => '<p>https://example.test/reset/secret-token</p>',
                'text' => 'https://example.test/reset/secret-token',
            ]),
            responseHeaders: [],
            responseBody: '{"id":"re_123"}',
        );

        Event::assertDispatched(MessageLogged::class, function (MessageLogged $event): bool {
            $encoded = json_encode($event->context);

            return $event->message === 'Outgoing API request'
                && $event->context['request']['subject'] === 'Reset Password Notification'
                && $event->context['request']['html'] === '[redacted]'
                && $event->context['request']['text'] === '[redacted]'
                && $event->context['response']['id'] === 're_123'
                && is_string($encoded)
                && ! str_contains($encoded, 're_live_secret')
                && ! str_contains($encoded, 'secret-token');
        });
    }

    public function test_unrelated_hosts_are_not_logged(): void
    {
        Event::fake([MessageLogged::class]);

        app(LogOutgoingApiCall::class)->record(
            method: 'GET',
            url: 'https://www.geoguessr.com/api/v3/scores',
            status: 200,
            requestHeaders: [],
            requestBody: '',
            responseHeaders: [],
            responseBody: '{}',
        );

        Event::assertNotDispatched(MessageLogged::class, function (MessageLogged $event): bool {
            return $event->message === 'Outgoing API request';
        });
    }
}
