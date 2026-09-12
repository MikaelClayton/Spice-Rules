<?php

namespace App\Services\Http;

use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class LogOutgoingApiCall
{
    /**
     * @var list<string>
     */
    private const SENSITIVE_HEADERS = [
        'authorization',
        'cookie',
        'proxy-authorization',
        'set-cookie',
        'x-api-key',
    ];

    /**
     * @var list<string>
     */
    private const SENSITIVE_BODY_KEYS = [
        'access_token',
        'api_key',
        'assertion',
        'attachments',
        'client_secret',
        'html',
        'private_key',
        'refresh_token',
        'text',
    ];

    public function fromLaravel(Request $request, ?Response $response, ?string $error = null): void
    {
        $this->record(
            method: $request->method(),
            url: $request->url(),
            status: $response?->status() ?? 0,
            requestHeaders: $request->headers(),
            requestBody: $request->body(),
            responseHeaders: $response?->headers() ?? [],
            responseBody: $response?->body(),
            error: $error,
        );
    }

    public function fromPsr(RequestInterface $request, ?ResponseInterface $response, ?string $error = null): void
    {
        $this->record(
            method: $request->getMethod(),
            url: (string) $request->getUri(),
            status: $response?->getStatusCode() ?? 0,
            requestHeaders: $request->getHeaders(),
            requestBody: (string) $request->getBody(),
            responseHeaders: $response?->getHeaders() ?? [],
            responseBody: $response !== null ? (string) $response->getBody() : null,
            error: $error,
        );

        if ($request->getBody()->isSeekable()) {
            $request->getBody()->rewind();
        }

        if ($response !== null && $response->getBody()->isSeekable()) {
            $response->getBody()->rewind();
        }
    }

    /**
     * @param  array<string, mixed>  $requestHeaders
     * @param  array<string, mixed>  $responseHeaders
     */
    public function record(
        string $method,
        string $url,
        int $status,
        array $requestHeaders,
        mixed $requestBody,
        array $responseHeaders,
        mixed $responseBody,
        ?string $error = null,
    ): void {
        if (! $this->shouldLog($url)) {
            return;
        }

        $context = [
            'method' => $method,
            'url' => $url,
            'status' => $status,
            'request_headers' => $this->redactHeaders($requestHeaders),
            'request' => $this->decodeBody($requestBody),
            'response_headers' => $this->redactHeaders($responseHeaders),
            'response' => $this->decodeBody($responseBody),
        ];

        if ($error !== null) {
            $context['error'] = $error;
        }

        $level = $error !== null || $status >= 400 || $status === 0 ? 'warning' : 'info';

        Log::log($level, 'Outgoing API request', $context);
    }

    private function shouldLog(string $url): bool
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));

        if ($host === '') {
            return false;
        }

        $allowed = [
            'fcm.googleapis.com',
            'oauth2.googleapis.com',
            'api.resend.com',
        ];

        $configuredResend = (string) config('services.resend.base_url', 'api.resend.com');
        $configuredHost = strtolower((string) parse_url(
            str_contains($configuredResend, '://') ? $configuredResend : 'https://'.$configuredResend,
            PHP_URL_HOST,
        ));

        if ($configuredHost !== '') {
            $allowed[] = $configuredHost;
        }

        return in_array($host, $allowed, true);
    }

    /**
     * @param  array<string, mixed>  $headers
     * @return array<string, mixed>
     */
    private function redactHeaders(array $headers): array
    {
        $redacted = [];

        foreach ($headers as $name => $value) {
            if (in_array(strtolower((string) $name), self::SENSITIVE_HEADERS, true)) {
                $redacted[$name] = is_array($value) ? ['[redacted]'] : '[redacted]';

                continue;
            }

            $redacted[$name] = $value;
        }

        return $redacted;
    }

    private function decodeBody(mixed $body): mixed
    {
        if (is_array($body)) {
            return $this->redactArray($body);
        }

        if (! is_string($body) || $body === '') {
            return $body;
        }

        $trimmed = ltrim($body);

        if (str_starts_with($trimmed, '{') || str_starts_with($trimmed, '[')) {
            $json = json_decode($body, true);

            if (json_last_error() === JSON_ERROR_NONE && is_array($json)) {
                return $this->redactArray($json);
            }
        }

        if (str_contains($body, '=') && ! str_starts_with($trimmed, '{')) {
            parse_str($body, $form);

            if ($form !== []) {
                return $this->redactArray($form);
            }
        }

        return Str::limit($body, 2000);
    }

    /**
     * @param  array<array-key, mixed>  $payload
     * @return array<array-key, mixed>
     */
    private function redactArray(array $payload): array
    {
        $redacted = [];

        foreach ($payload as $key => $value) {
            if (is_string($key) && in_array(strtolower($key), self::SENSITIVE_BODY_KEYS, true)) {
                $redacted[$key] = '[redacted]';

                continue;
            }

            $redacted[$key] = is_array($value) ? $this->redactArray($value) : $value;
        }

        return $redacted;
    }
}
