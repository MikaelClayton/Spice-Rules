<?php

namespace App\Console\Commands;

use App\Services\Cron\RecordCronRun;
use App\Services\Spirdle\EnsureTodaysSpirdlePuzzle;
use Illuminate\Console\Command;

class OpenSpirdleDailyCommand extends Command
{
    protected $signature = 'spirdle:open-daily';

    protected $description = 'Open today\'s Spirdle puzzle from the answer list';

    public function handle(EnsureTodaysSpirdlePuzzle $ensure, RecordCronRun $recordCronRun): int
    {
        $puzzle = $recordCronRun->handle(
            'spirdle:open-daily',
            function () use ($ensure): array {
                $puzzle = $ensure->handle();

                return [
                    'synced' => $puzzle === null ? 0 : 1,
                    'puzzle' => $puzzle,
                ];
            },
        )['puzzle'] ?? null;

        if ($puzzle === null) {
            $this->error('No Spirdle answers are loaded. Run php artisan spirdle:import-words first.');

            return self::FAILURE;
        }

        $this->info('Today\'s Spirdle is ready.');

        return self::SUCCESS;
    }
}
