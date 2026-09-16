<?php

namespace Tests\Unit\Services\FitIsh;

use App\Models\FitIshSession;
use App\Models\FitIshSessionZone;
use App\Models\User;
use App\Services\FitIsh\BuildFitIshToday;
use App\Services\FitIsh\BuildFitIshZoneRadar;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BuildFitIshZoneRadarTest extends TestCase
{
    use RefreshDatabase;

    public function test_people_radar_uses_each_players_best_session_and_board_colour(): void
    {
        $ada = User::factory()->create([
            'name' => 'Ada Lovelace',
            'color' => '#2A9D8F',
        ]);
        $ben = User::factory()->create([
            'name' => 'Ben',
            'color' => '#E85D04',
        ]);
        $adaBest = FitIshSession::factory()->create([
            'user_id' => $ada->id,
            'points' => 51.2,
        ]);
        $adaWarmup = FitIshSession::factory()->create([
            'user_id' => $ada->id,
            'points' => 20.0,
        ]);
        $benSession = FitIshSession::factory()->create([
            'user_id' => $ben->id,
            'points' => 40.0,
        ]);
        FitIshSessionZone::factory()->create([
            'fit_ish_session_id' => $adaBest->id,
            'zone_number' => 1,
            'percentage_value' => 15,
        ]);
        FitIshSessionZone::factory()->create([
            'fit_ish_session_id' => $adaBest->id,
            'zone_number' => 5,
            'percentage_value' => 30,
        ]);
        FitIshSessionZone::factory()->create([
            'fit_ish_session_id' => $adaWarmup->id,
            'zone_number' => 1,
            'percentage_value' => 99,
        ]);
        FitIshSessionZone::factory()->create([
            'fit_ish_session_id' => $benSession->id,
            'zone_number' => 1,
            'percentage_value' => 50,
        ]);

        $radar = app(BuildFitIshZoneRadar::class)->forPeople(
            FitIshSession::query()
                ->with(['user', 'zones'])
                ->orderByDesc('points')
                ->orderBy('id')
                ->get(),
        );

        $this->assertSame('Ada', $radar['datasets'][0]['label']);
        $this->assertSame('#2A9D8F', $radar['datasets'][0]['color']);
        $this->assertSame(15.0, $radar['datasets'][0]['values'][0]);
        $this->assertSame(30.0, $radar['datasets'][0]['values'][4]);
        $this->assertSame('Ben', $radar['datasets'][1]['label']);
        $this->assertSame('#E85D04', $radar['datasets'][1]['color']);
        $this->assertSame(50.0, $radar['datasets'][1]['values'][0]);
    }

    public function test_today_board_includes_a_people_radar(): void
    {
        $this->travelTo('2026-09-16 08:00:00');

        $user = User::factory()->create(['name' => 'Wyn']);
        $session = FitIshSession::factory()->create([
            'user_id' => $user->id,
            'class_date' => '2026-09-16',
            'points' => 48.0,
        ]);
        FitIshSessionZone::factory()->create([
            'fit_ish_session_id' => $session->id,
            'zone_number' => 3,
            'percentage_value' => 22.5,
        ]);

        $board = app(BuildFitIshToday::class)->handle();

        $this->assertSame('Wyn', $board['zoneRadar']['datasets'][0]['label']);
        $this->assertSame(22.5, $board['zoneRadar']['datasets'][0]['values'][2]);
    }
}
