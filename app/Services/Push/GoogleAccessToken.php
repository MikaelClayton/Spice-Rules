<?php

namespace App\Services\Push;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GoogleAccessToken
{
    public function fetch(): ?string
    {
        $projectId = (string) config('services.firebase.project_id');
        $cacheKey = 'firebase.fcm_access_token.'.$projectId;
        $cached = Cache::get($cacheKey);

        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $token = $this->requestToken();

        if ($token !== null) {
            Cache::put($cacheKey, $token, now()->addMinutes(50));
        }

        return $token;
    }

    public function forget(): void
    {
        Cache::forget('firebase.fcm_access_token.'.config('services.firebase.project_id'));
    }

    private function requestToken(): ?string
    {
        $assertion = $this->signedJwt();

        if ($assertion === null) {
            return null;
        }

        try {
            $response = Http::asForm()
                ->connectTimeout(3)
                ->timeout(10)
                ->post('https://oauth2.googleapis.com/token', [
                    'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                    'assertion' => $assertion,
                ]);
        } catch (ConnectionException $exception) {
            Log::warning('Firebase access token request could not connect', [
                'message' => $exception->getMessage(),
            ]);

            return null;
        }

        $token = $response->json('access_token');

        if ($response->successful() && is_string($token) && $token !== '') {
            return $token;
        }

        Log::warning('Firebase access token request failed', [
            'status' => $response->status(),
        ]);

        return null;
    }

    private function signedJwt(): ?string
    {
        $now = time();
        $header = $this->base64UrlEncode((string) json_encode([
            'alg' => 'RS256',
            'typ' => 'JWT',
        ]));
        $claims = $this->base64UrlEncode((string) json_encode([
            'iss' => config('services.firebase.client_email'),
            'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
            'aud' => 'https://oauth2.googleapis.com/token',
            'iat' => $now,
            'exp' => $now + 3600,
        ]));
        $unsigned = $header.'.'.$claims;
        $privateKey = (string) config('services.firebase.private_key');

        if (! openssl_sign($unsigned, $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
            Log::warning('Could not sign Firebase access token.');

            return null;
        }

        return $unsigned.'.'.$this->base64UrlEncode($signature);
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
