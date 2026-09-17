<?php

namespace App\Services\Push;

use App\Models\DeviceToken;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FcmClient
{
    public function __construct(
        private readonly FirebaseConfig $firebase,
        private readonly GoogleAccessToken $accessToken,
    ) {}

    /**
     * @param  array{title: string, body: string, url: string}  $notification
     */
    public function sendToToken(string $token, array $notification): bool
    {
        if (! $this->firebase->isServerConfigured()) {
            return false;
        }

        $accessToken = $this->accessToken->fetch();

        if ($accessToken === null) {
            return false;
        }

        $response = $this->postMessage($accessToken, $token, $notification);

        if ($response !== null && $response->status() === 401) {
            $this->accessToken->forget();
            $accessToken = $this->accessToken->fetch();

            if ($accessToken === null) {
                return false;
            }

            $response = $this->postMessage($accessToken, $token, $notification);
        }

        if ($response === null) {
            return false;
        }

        if ($response->successful()) {
            return true;
        }

        if ($this->tokenIsInvalid($response)) {
            DeviceToken::query()->where('token', $token)->delete();

            return false;
        }

        Log::warning('FCM send failed', [
            'status' => $response->status(),
        ]);

        return false;
    }

    /**
     * @param  iterable<int, mixed>  $tokens
     * @param  array{title: string, body: string, url: string}  $notification
     * @return array{sent: int, failed: int}
     */
    public function sendToTokens(iterable $tokens, array $notification): array
    {
        $deviceTokens = Collection::make($tokens)
            ->map(fn (mixed $token): string => (string) $token)
            ->filter()
            ->unique()
            ->values();

        if ($deviceTokens->isEmpty()) {
            return [
                'sent' => 0,
                'failed' => 0,
            ];
        }

        if (! $this->firebase->isServerConfigured()) {
            return [
                'sent' => 0,
                'failed' => $deviceTokens->count(),
            ];
        }

        $accessToken = $this->accessToken->fetch();

        if ($accessToken === null) {
            return [
                'sent' => 0,
                'failed' => $deviceTokens->count(),
            ];
        }

        $responses = $this->postMessages($accessToken, $deviceTokens, $notification);

        if ($this->anyUnauthorized($responses)) {
            $this->accessToken->forget();
            $accessToken = $this->accessToken->fetch();

            if ($accessToken !== null) {
                $responses = $this->postMessages($accessToken, $deviceTokens, $notification);
            }
        }

        $sent = 0;
        $failed = 0;

        foreach ($deviceTokens as $token) {
            $response = $responses[$token] ?? null;

            if ($response instanceof Response && $response->successful()) {
                $sent++;

                continue;
            }

            $failed++;

            if ($response instanceof ConnectionException) {
                Log::warning('FCM send could not connect', [
                    'message' => $response->getMessage(),
                ]);

                continue;
            }

            if ($response instanceof Response && $this->tokenIsInvalid($response)) {
                DeviceToken::query()->where('token', $token)->delete();

                continue;
            }

            if ($response instanceof Response) {
                Log::warning('FCM send failed', [
                    'status' => $response->status(),
                ]);
            }
        }

        return [
            'sent' => $sent,
            'failed' => $failed,
        ];
    }

    /**
     * @param  array{title: string, body: string, url: string}  $notification
     */
    private function postMessage(string $accessToken, string $deviceToken, array $notification): ?Response
    {
        try {
            return Http::withToken($accessToken)
                ->connectTimeout(3)
                ->timeout(10)
                ->acceptJson()
                ->post($this->messagesUrl(), $this->messagePayload($deviceToken, $notification));
        } catch (ConnectionException $exception) {
            Log::warning('FCM send could not connect', [
                'message' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * @param  Collection<int, string>  $deviceTokens
     * @param  array{title: string, body: string, url: string}  $notification
     * @return array<string, Response|ConnectionException>
     */
    private function postMessages(string $accessToken, Collection $deviceTokens, array $notification): array
    {
        $url = $this->messagesUrl();

        return Http::pool(function (Pool $pool) use ($accessToken, $deviceTokens, $notification, $url): void {
            foreach ($deviceTokens as $token) {
                $pool->as($token)
                    ->withToken($accessToken)
                    ->connectTimeout(3)
                    ->timeout(10)
                    ->acceptJson()
                    ->post($url, $this->messagePayload($token, $notification));
            }
        });
    }

    /**
     * @param  array<string, mixed>  $responses
     */
    private function anyUnauthorized(array $responses): bool
    {
        foreach ($responses as $response) {
            if ($response instanceof Response && $response->status() === 401) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array{title: string, body: string, url: string}  $notification
     * @return array{message: array<string, mixed>}
     */
    private function messagePayload(string $deviceToken, array $notification): array
    {
        return [
            'message' => [
                'token' => $deviceToken,
                'notification' => [
                    'title' => $notification['title'],
                    'body' => $notification['body'],
                ],
                'webpush' => [
                    'fcm_options' => [
                        'link' => $notification['url'],
                    ],
                    'notification' => [
                        'title' => $notification['title'],
                        'body' => $notification['body'],
                        'icon' => url('/favicon.png'),
                    ],
                ],
            ],
        ];
    }

    private function messagesUrl(): string
    {
        return 'https://fcm.googleapis.com/v1/projects/'.config('services.firebase.project_id').'/messages:send';
    }

    private function tokenIsInvalid(Response $response): bool
    {
        if ($response->status() === 404) {
            return true;
        }

        $status = $response->json('error.status');
        $code = $response->json('error.details.0.errorCode');

        return in_array($status, ['NOT_FOUND', 'INVALID_ARGUMENT'], true)
            || $code === 'UNREGISTERED';
    }
}
