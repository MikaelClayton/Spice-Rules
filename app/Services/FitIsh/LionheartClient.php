<?php

namespace App\Services\FitIsh;

use App\Models\OutgoingApiCall;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class LionheartClient
{
    private string $source = 'fit-ish';

    private ?int $cronRunId = null;

    public function using(string $source, ?int $cronRunId = null): self
    {
        $this->source = $source;
        $this->cronRunId = $cronRunId;

        return $this;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function session(string $sessionId, string $userId): ?array
    {
        return $this->get('/v3/sessions/'.$sessionId, [
            'user_id' => $userId,
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function profileSummary(string $userId): ?array
    {
        return $this->get('/v3/profile/sessions/summary', [
            'user_id' => $userId,
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function profileSessions(string $userId, int $skip = 0): ?array
    {
        $query = ['user_id' => $userId];

        if ($skip > 0) {
            $query['skip'] = $skip;
        }

        return $this->get('/v3/profile/sessions', $query);
    }

    /**
     * @return array<int, string>
     */
    public function sessionIdsFrom(mixed $payload): array
    {
        if (! is_array($payload)) {
            return [];
        }

        $rows = $payload['data'] ?? $payload;
        $candidates = [];

        if (is_array($rows)) {
            $nested = $rows['sessions'] ?? $rows['items'] ?? $rows['results'] ?? null;

            if (is_array($nested)) {
                $candidates = $nested;
            } elseif (array_is_list($rows)) {
                $candidates = $rows;
            }
        }

        $ids = [];

        foreach ($candidates as $row) {
            if (is_string($row) && $this->looksLikeSessionId($row)) {
                $ids[] = $row;

                continue;
            }

            if (! is_array($row)) {
                continue;
            }

            $sessionId = $row['sessionId'] ?? $row['session_id'] ?? $row['id'] ?? null;

            if (is_string($sessionId) && $this->looksLikeSessionId($sessionId)) {
                $ids[] = $sessionId;
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * @param  array<string, scalar>  $query
     * @return array<string, mixed>|null
     */
    private function get(string $path, array $query = []): ?array
    {
        $url = rtrim((string) config('fit-ish.base_url'), '/').$path;

        if ($query !== []) {
            $url .= (str_contains($url, '?') ? '&' : '?').http_build_query($query);
        }

        $started = hrtime(true);
        $status = null;
        $succeeded = false;
        $error = null;
        $responsePayload = null;

        try {
            $pending = Http::acceptJson()
                ->connectTimeout(3)
                ->timeout((int) config('fit-ish.timeout', 20))
                ->retry([100, 500, 1000], 0, function (\Throwable $exception): bool {
                    return $exception instanceof ConnectionException
                        || ($exception instanceof RequestException
                            && ($exception->response?->serverError() || $exception->response?->status() === 429));
                });

            $token = config('fit-ish.token');

            if (filled($token)) {
                $pending = $pending->withToken((string) $token);
            }

            $response = $pending->get($url);
            $status = $response->status();
            $responsePayload = $this->payload($response);

            if ($response->notFound() || $response->status() === 401 || $response->status() === 403) {
                return null;
            }

            $response->throw();
            $succeeded = true;

            if (($responsePayload['success'] ?? true) === false) {
                return null;
            }

            return $responsePayload;
        } catch (RequestException $exception) {
            $status = $exception->response?->status() ?? $status;
            $error = $exception->getMessage();
            $responsePayload = $exception->response ? $this->payload($exception->response) : $responsePayload;

            if (in_array($status, [401, 403, 404], true)) {
                return null;
            }

            throw $exception;
        } catch (ConnectionException $exception) {
            $error = $exception->getMessage();

            throw $exception;
        } finally {
            $this->recordCall(
                method: 'GET',
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
     * @return array<string, mixed>
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
     * @param  array<string, mixed>|null  $response
     */
    private function recordCall(
        string $method,
        string $url,
        ?int $status,
        bool $succeeded,
        int $durationMs,
        ?string $error,
        ?array $response,
    ): void {
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
        ]);
    }

    private function looksLikeSessionId(string $value): bool
    {
        return preg_match('/^\d{4}-\d{2}-\d{2}_\d{4}:studio:[^:]+:serial:.+$/', $value) === 1;
    }
}
