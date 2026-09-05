<?php

namespace App\Services\Geoguessr;

use App\Models\OutgoingApiCall;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class GeoguessrClient
{
    private string $source = 'geoguessr';

    private ?int $cronRunId = null;

    private ?int $geoguesserId = null;

    public function __construct(private readonly string $baseUrl = 'https://www.geoguessr.com') {}

    public function using(string $source, ?int $cronRunId = null, ?int $geoguesserId = null): self
    {
        $this->source = $source;
        $this->cronRunId = $cronRunId;
        $this->geoguesserId = $geoguesserId;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function profile(string $ncfa): array
    {
        return $this->get('/api/v3/profiles', $ncfa);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function weeklyDailyChallenges(string $ncfa): array
    {
        return $this->get('/api/v3/challenges/daily-challenges/me/week', $ncfa);
    }

    /**
     * @return array<string, mixed>
     */
    public function stats(string $ncfa): array
    {
        return $this->get('/api/v3/profiles/stats', $ncfa);
    }

    /**
     * @return array<string, mixed>
     */
    public function challengeGame(string $ncfa, string $challengeToken): array
    {
        return $this->request('POST', '/api/v3/challenges/'.$challengeToken, $ncfa);
    }

    public static function normalizeNcfa(string $ncfa): string
    {
        $ncfa = trim($ncfa);
        $ncfa = trim($ncfa, "\"'");

        if (str_starts_with($ncfa, '_ncfa=')) {
            $ncfa = substr($ncfa, strlen('_ncfa='));
        }

        return $ncfa;
    }

    /**
     * @return array<string, mixed>|list<mixed>
     */
    private function get(string $path, string $ncfa): array
    {
        return $this->request('GET', $path, $ncfa);
    }

    /**
     * @return array<string, mixed>|list<mixed>
     */
    private function request(string $method, string $path, string $ncfa): array
    {
        $url = $this->baseUrl.$path;
        $started = hrtime(true);
        $status = null;
        $succeeded = false;
        $error = null;
        $responsePayload = null;

        try {
            $pending = Http::acceptJson()
                ->timeout(20)
                ->withHeaders([
                    'Cookie' => '_ncfa='.self::normalizeNcfa($ncfa),
                ])
                ->withOptions([
                    'proxy' => '',
                ]);

            $response = strtoupper($method) === 'POST'
                ? $pending->post($url)
                : $pending->get($url);

            $status = $response->status();
            $responsePayload = $this->payload($response);
            $response->throw();
            $succeeded = true;

            return $responsePayload;
        } catch (RequestException $exception) {
            $status = $exception->response?->status() ?? $status;
            $error = $exception->getMessage();
            $responsePayload = $exception->response ? $this->payload($exception->response) : $responsePayload;

            throw $exception;
        } catch (ConnectionException $exception) {
            $error = $exception->getMessage();

            throw $exception;
        } finally {
            $this->recordCall(
                method: strtoupper($method),
                url: $url,
                status: $status,
                succeeded: $succeeded,
                durationMs: (int) ((hrtime(true) - $started) / 1_000_000),
                error: $error,
                response: $responsePayload,
            );
        }
    }

    /**
     * @return array<string, mixed>|list<mixed>
     */
    private function payload(Response $response): array
    {
        $json = $response->json();

        if (is_array($json)) {
            return $json;
        }

        return ['body' => Str::limit($response->body(), 10_000)];
    }

    /**
     * @param  array<string, mixed>|list<mixed>|null  $response
     */
    private function recordCall(string $method, string $url, ?int $status, bool $succeeded, int $durationMs, ?string $error, ?array $response): void
    {
        OutgoingApiCall::query()->create([
            'source' => $this->source,
            'method' => $method,
            'url' => $url,
            'status_code' => $status,
            'succeeded' => $succeeded,
            'duration_ms' => $durationMs,
            'error_message' => $error,
            'response' => $response,
            'cron_run_id' => $this->cronRunId,
            'geoguesser_id' => $this->geoguesserId,
        ]);
    }
}
