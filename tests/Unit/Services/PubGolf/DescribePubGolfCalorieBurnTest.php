<?php

namespace Tests\Unit\Services\PubGolf;

use App\Services\PubGolf\DescribePubGolfCalorieBurn;
use Tests\TestCase;

class DescribePubGolfCalorieBurnTest extends TestCase
{
    public function test_zero_calories_returns_null(): void
    {
        $this->assertNull((new DescribePubGolfCalorieBurn)->handle(0));
    }

    public function test_it_estimates_zone_2_minutes_with_shown_math(): void
    {
        $burn = (new DescribePubGolfCalorieBurn)->handle(1200);

        $this->assertSame([
            'calories' => 1200,
            'minutes' => 147,
            'kcal_per_minute' => 8.17,
            'met' => 7.0,
            'weight_kg' => 70,
            'equation' => '1,200 ÷ (7 × 70 ÷ 60) = 1,200 ÷ 8.17 ≈ 147 minutes',
            'summary' => 'About 147 minutes of zone 2 running at 70 kg (7 MET).',
        ], $burn);
    }

    public function test_it_accepts_a_custom_weight(): void
    {
        $burn = (new DescribePubGolfCalorieBurn)->handle(1040, weightKg: 80);

        $this->assertSame(111, $burn['minutes']);
        $this->assertSame(80, $burn['weight_kg']);
        $this->assertSame(7.0, $burn['met']);
        $this->assertSame('About 111 minutes of zone 2 running at 80 kg (7 MET).', $burn['summary']);
        $this->assertSame('1,040 ÷ (7 × 80 ÷ 60) = 1,040 ÷ 9.33 ≈ 111 minutes', $burn['equation']);
    }
}
