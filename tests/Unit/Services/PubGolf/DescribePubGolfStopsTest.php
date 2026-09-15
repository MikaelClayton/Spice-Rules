<?php

namespace Tests\Unit\Services\PubGolf;

use App\Models\PubGolfDrinkLog;
use App\Services\PubGolf\DescribePubGolfStops;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Tests\TestCase;

class DescribePubGolfStopsTest extends TestCase
{
    public function test_consecutive_venues_merge_and_a_gap_starts_a_new_stop(): void
    {
        $stops = (new DescribePubGolfStops)->handle(Collection::make([
            $this->log('Oppie Stoep', '2026-09-14 16:00:00'),
            $this->log('oppie stoep', '2026-09-14 16:20:00'),
            $this->log(null, '2026-09-14 16:35:00'),
            $this->log('Oppie Stoep', '2026-09-14 16:50:00'),
            $this->log('The Aroma', '2026-09-14 17:10:00'),
        ]), (string) config('app.timezone'));

        $this->assertSame([
            [
                'location' => 'Oppie Stoep',
                'drink_count' => 2,
                'drink_label' => '2 drinks',
                'when' => '16:00–16:20',
            ],
            [
                'location' => 'Oppie Stoep',
                'drink_count' => 1,
                'drink_label' => '1 drink',
                'when' => '16:50',
            ],
            [
                'location' => 'The Aroma',
                'drink_count' => 1,
                'drink_label' => '1 drink',
                'when' => '17:10',
            ],
        ], $stops);
    }

    public function test_logs_without_a_venue_are_omitted(): void
    {
        $stops = (new DescribePubGolfStops)->handle(Collection::make([
            $this->log(null, '2026-09-14 16:00:00'),
        ]), (string) config('app.timezone'));

        $this->assertSame([], $stops);
    }

    private function log(?string $location, string $time): PubGolfDrinkLog
    {
        $log = new PubGolfDrinkLog;
        $log->location = $location;
        $log->created_at = Carbon::parse($time);

        return $log;
    }
}
