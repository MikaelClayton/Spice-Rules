<?php

namespace Tests\Unit\Models;

use App\Models\FitIshWorkout;
use Tests\TestCase;

class FitIshWorkoutTest extends TestCase
{
    public function test_stored_logos_use_local_storage_instead_of_the_remote_cdn(): void
    {
        $workout = FitIshWorkout::factory()->make([
            'logo_path' => 'fit-ish/workouts/phoenix.png',
            'logo_url' => 'https://f45tv.cdn.f45.com/lionheart-logos/F45_Logos_Phoenix_600x600.png',
        ]);

        $this->assertStringContainsString('/storage/fit-ish/workouts/phoenix.png', (string) $workout->logoUrl());
        $this->assertStringNotContainsString('f45tv.cdn.f45.com', (string) $workout->logoUrl());
    }

    public function test_falls_back_to_the_remote_logo_when_nothing_is_stored(): void
    {
        $workout = FitIshWorkout::factory()->make([
            'logo_path' => null,
            'logo_url' => 'https://f45tv.cdn.f45.com/lionheart-logos/F45_Logos_Phoenix_600x600.png',
        ]);

        $this->assertSame(
            'https://f45tv.cdn.f45.com/lionheart-logos/F45_Logos_Phoenix_600x600.png',
            $workout->logoUrl(),
        );
    }
}
