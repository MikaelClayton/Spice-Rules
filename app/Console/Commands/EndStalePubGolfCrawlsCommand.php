<?php

namespace App\Console\Commands;

use App\Services\Cron\RecordCronRun;
use App\Services\PubGolf\EndStalePubGolfCrawls;
use Illuminate\Console\Command;

class EndStalePubGolfCrawlsCommand extends Command
{
    protected $signature = 'pub-golf:end-stale';

    protected $description = 'End open crawls that have had no drinks, joins, or leaves for four hours';

    public function handle(EndStalePubGolfCrawls $endStalePubGolfCrawls, RecordCronRun $recordCronRun): int
    {
        $ended = $recordCronRun->handle(
            'pub-golf:end-stale',
            fn (): int => $endStalePubGolfCrawls->handle(),
        );

        $this->info($ended === 1 ? 'Ended 1 stale crawl.' : "Ended {$ended} stale crawls.");

        return self::SUCCESS;
    }
}
