<?php

namespace App\Services\Push;

use App\Models\DeviceToken;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

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
        $sent = 0;
        $failed = 0;

        foreach ($tokens as $token) {
            try {
                if ($this->sendToToken((string) $token, $notification)) {
                    $sent++;
                } else {
                    $failed++;
                }
            } catch (Throwable $exception) {
                Log::warning('FCM send failed', [
                    'message' => $exception->getMessage(),
                ]);
                $failed++;
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
        $projectId = (string) config('services.firebase.project_id');

        try {
            return Http::withToken($accessToken)
                ->connectTimeout(3)
                ->timeout(10)
                ->acceptJson()
                ->post('https://fcm.googleapis.com/v1/projects/'.$projectId.'/messages:send', [
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
                ]);
        } catch (ConnectionException $exception) {
            Log::warning('FCM send could not connect', [
                'message' => $exception->getMessage(),
            ]);

            return null;
        }
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
