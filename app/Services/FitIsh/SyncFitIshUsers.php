<?php

namespace App\Services\FitIsh;

use App\Models\CronRun;
use App\Models\FitIshSession;
use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Log;

class SyncFitIshUsers
{
    public function __construct(
        private readonly LionheartClient $client,
        private readonly PersistFitIshSession $sessions,
        private readonly PersistFitIshProfileSummary $summaries,
    ) {}

    /**
     * @return array{synced: int, skipped: int, sessions: int}
     */
    public function handle(?CronRun $cronRun = null, bool $force = false, ?User $only = null): array
    {
        $query = User::query()
            ->with('fitIshStudios')
            ->whereNotNull('fit_ish_user_id')
            ->where('fit_ish_user_id', '!=', '')
            ->orderBy('id');

        if ($only !== null) {
            $query->whereKey($only->id);
        }

        $synced = 0;
        $skipped = 0;
        $sessionCount = 0;

        foreach ($query->get() as $user) {
            try {
                $imported = $this->sync($user, $cronRun, $force);
                $sessionCount += $imported;
                $synced++;
            } catch (RequestException|ConnectionException $exception) {
                $skipped++;
                Log::warning('Fit-Ish sync failed', [
                    'user_id' => $user->id,
                    'status' => $exception instanceof RequestException ? $exception->response?->status() : null,
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        return [
            'synced' => $synced,
            'skipped' => $skipped,
            'sessions' => $sessionCount,
        ];
    }

    public function sync(User $user, ?CronRun $cronRun = null, bool $force = false): int
    {
        $client = $this->client->using('fit-ish_sync', $cronRun?->id);
        $userId = (string) $user->fit_ish_user_id;
        $imported = 0;

        $summary = $client->profileSummary($userId);

        if ($summary !== null) {
            $this->summaries->handle($user, $summary);
        }

        foreach ($this->sessionIdsFor($client, $user, $force) as $sessionId) {
            if (! $force && $this->alreadySaved($sessionId) && ! $this->isRecent($sessionId)) {
                continue;
            }

            $payload = $client->session($sessionId, $userId);

            if ($payload === null) {
                continue;
            }

            if ($this->sessions->handle($user, $payload) !== null) {
                $imported++;
            }
        }

        return $imported;
    }

    /**
     * @return list<string>
     */
    private function sessionIdsFor(LionheartClient $client, User $user, bool $force): array
    {
        $ids = $this->listedSessionIds($client, (string) $user->fit_ish_user_id);

        if ($ids !== []) {
            return $ids;
        }

        return $this->probedSessionIds($user);
    }

    /**
     * @return list<string>
     */
    private function listedSessionIds(LionheartClient $client, string $userId): array
    {
        $ids = [];
        $skip = 0;

        for ($page = 0; $page < 25; $page++) {
            $payload = $client->profileSessions($userId, $skip);

            if ($payload === null) {
                break;
            }

            $pageIds = $client->sessionIdsFrom($payload);

            if ($pageIds === []) {
                break;
            }

            $fresh = array_values(array_diff($pageIds, $ids));

            if ($fresh === []) {
                break;
            }

            foreach ($fresh as $sessionId) {
                $ids[] = $sessionId;
            }

            $skip += count($pageIds);
        }

        return array_values(array_unique($ids));
    }

    /**
     * @return list<string>
     */
    private function probedSessionIds(User $user): array
    {
        $serial = $user->fit_ish_serial;
        $studios = $user->fitIshStudios;

        if (blank($serial) || $studios->isEmpty()) {
            return [];
        }

        $times = config('fit-ish.class_times', []);
        $lookback = max(1, (int) config('fit-ish.lookback_days', 2));
        $ids = [];

        for ($offset = 0; $offset < $lookback; $offset++) {
            $date = today()->subDays($offset)->toDateString();

            foreach ($studios as $studio) {
                foreach ($times as $time) {
                    $ids[] = $date.'_'.$time.':studio:'.$studio->code.':serial:'.$serial;
                }
            }
        }

        return $ids;
    }

    private function alreadySaved(string $sessionId): bool
    {
        return FitIshSession::query()->where('session_id', $sessionId)->exists();
    }

    private function isRecent(string $sessionId): bool
    {
        $date = substr($sessionId, 0, 10);

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) !== 1) {
            return true;
        }

        return $date >= today()->subDays((int) config('fit-ish.lookback_days', 2))->toDateString()
            && $date <= today()->toDateString();
    }
}
