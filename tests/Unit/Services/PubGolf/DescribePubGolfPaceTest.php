<?php

namespace Tests\Unit\Services\PubGolf;

use App\Services\PubGolf\DescribePubGolfPace;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class DescribePubGolfPaceTest extends TestCase
{
    #[TestWith([0, 3600, 'Warming up', 0, 'neutral'])]
    #[TestWith([1, 7200, 'Sipping', 1, 'info'])]
    #[TestWith([2, 7200, 'Steady', 2, 'success'])]
    #[TestWith([3, 3600, 'Moving', 3, 'warning'])]
    #[TestWith([4, 3600, 'On one', 4, 'secondary'])]
    #[TestWith([6, 3600, 'Legendary', 5, 'primary'])]
    public function test_snapshot_maps_drinks_per_hour_onto_a_pace_step(
        int $drinks,
        int $durationSeconds,
        string $label,
        int $level,
        string $tone,
    ): void {
        $pace = (new DescribePubGolfPace)->snapshot($drinks, $durationSeconds);

        $this->assertSame($label, $pace['label']);
        $this->assertSame($level, $pace['level']);
        $this->assertSame($tone, $pace['tone']);
        $this->assertSame([
            'Warming up',
            'Sipping',
            'Steady',
            'Moving',
            'On one',
            'Legendary',
        ], $pace['steps']);
    }
}
