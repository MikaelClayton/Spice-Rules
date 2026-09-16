<?php

namespace Tests\Unit\Models;

use App\Models\FitIshSessionZone;
use Tests\TestCase;

class FitIshSessionZoneTest extends TestCase
{
    public function test_short_name_uses_the_label_after_the_slash(): void
    {
        $zone = new FitIshSessionZone([
            'zone_number' => 1,
            'name' => 'Very light / Recovery',
        ]);

        $this->assertSame('Recovery', $zone->shortName());
    }

    public function test_short_name_keeps_a_plain_zone_name(): void
    {
        $zone = new FitIshSessionZone([
            'zone_number' => 4,
            'name' => 'High',
        ]);

        $this->assertSame('High', $zone->shortName());
    }

    public function test_invalid_hex_falls_back_to_a_neutral_swatch(): void
    {
        $zone = new FitIshSessionZone([
            'color_hex' => 'red',
        ]);

        $this->assertSame('#888888', $zone->swatch());
    }
}
