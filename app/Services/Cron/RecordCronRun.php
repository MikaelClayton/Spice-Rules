<?php

namespace App\Services\Cron;

use App\Models\CronRun;
use Throwable;

class RecordCronRun
{
    /**
     * @template TResult
     *
     * @param  callable(CronRun): TResult  $callback
     * @return TResult
     */
    public function handle(string $command, callable $callback): mixed
    {
        $started = hrtime(true);
        $run = CronRun::query()->create([
            'command' => $command,
            'status' => 'running',
            'profiles_synced' => 0,
            'duration_ms' => 0,
            'started_at' => now(),
        ]);

        try {
            $result = $callback($run);
            $run->update([
                'status' => 'success',
                'profiles_synced' => $this->profilesSynced($result),
                'duration_ms' => $this->elapsedMilliseconds($started),
                'finished_at' => now(),
            ]);

            return $result;
        } catch (Throwable $exception) {
            $run->update([
                'status' => 'failed',
                'duration_ms' => $this->elapsedMilliseconds($started),
                'error_message' => $exception->getMessage(),
                'finished_at' => now(),
            ]);

            throw $exception;
        }
    }

    private function profilesSynced(mixed $result): int
    {
        if (! is_array($result)) {
            return 0;
        }

        $synced = $result['synced'] ?? $result['profiles_synced'] ?? 0;

        return is_numeric($synced) ? (int) $synced : 0;
    }

    private function elapsedMilliseconds(int $started): int
    {
        return (int) ((hrtime(true) - $started) / 1_000_000);
    }
}
