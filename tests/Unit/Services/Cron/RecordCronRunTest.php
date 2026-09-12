<?php

namespace Tests\Unit\Services\Cron;

use App\Models\CronRun;
use App\Services\Cron\RecordCronRun;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class RecordCronRunTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_records_a_successful_command_run(): void
    {
        $result = app(RecordCronRun::class)->handle('pub-golf:end-stale', fn (): int => 2);

        $this->assertSame(2, $result);
        $this->assertDatabaseHas('cron_runs', [
            'command' => 'pub-golf:end-stale',
            'status' => 'success',
            'profiles_synced' => 0,
        ]);
        $this->assertNotNull(CronRun::query()->value('finished_at'));
        $this->assertGreaterThanOrEqual(0, CronRun::query()->value('duration_ms'));
    }

    public function test_it_stores_geoguessr_profile_counts(): void
    {
        app(RecordCronRun::class)->handle('geoguessr:sync', fn (): array => [
            'synced' => 3,
            'skipped' => 1,
        ]);

        $this->assertDatabaseHas('cron_runs', [
            'command' => 'geoguessr:sync',
            'status' => 'success',
            'profiles_synced' => 3,
        ]);
    }

    public function test_it_records_a_failed_command_run(): void
    {
        try {
            app(RecordCronRun::class)->handle('geoguessr:sync', function (): void {
                throw new RuntimeException('GeoGuessr is down.');
            });
            $this->fail('The callback exception should be rethrown.');
        } catch (RuntimeException $exception) {
            $this->assertSame('GeoGuessr is down.', $exception->getMessage());
        }

        $this->assertDatabaseHas('cron_runs', [
            'command' => 'geoguessr:sync',
            'status' => 'failed',
            'error_message' => 'GeoGuessr is down.',
        ]);
        $this->assertNotNull(CronRun::query()->value('finished_at'));
    }
}
