<?php

namespace Tests\Feature;

use App\Models\FitIshSession;
use App\Models\FitIshSessionGraphPoint;
use App\Models\FitIshSessionZone;
use App\Models\FitIshStudio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FitIshDayTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_load_a_session_day(): void
    {
        $this->getJson(route('fit-ish.days.show', ['date' => '2026-09-14']))
            ->assertUnauthorized();
    }

    public function test_users_without_fit_ish_cannot_load_a_session_day(): void
    {
        $this->actingAs(User::factory()->create())
            ->getJson(route('fit-ish.days.show', ['date' => '2026-09-14']))
            ->assertNotFound();
    }

    public function test_session_day_includes_heart_rate_for_that_date_only(): void
    {
        $this->travelTo('2026-09-15 12:00:00');

        $user = User::factory()->withFitIsh()->create(['name' => 'Wyn']);
        $studio = FitIshStudio::factory()->faerieGlen()->create();
        $session = FitIshSession::factory()->create([
            'user_id' => $user->id,
            'fit_ish_studio_id' => $studio->id,
            'class_date' => '2026-09-14',
            'points' => 40.0,
            'session_id' => '2026-09-14_0600:studio:ojb7:serial:1352',
        ]);
        FitIshSession::factory()->create([
            'user_id' => $user->id,
            'fit_ish_studio_id' => $studio->id,
            'class_date' => '2026-09-15',
            'points' => 99.0,
            'session_id' => '2026-09-15_0600:studio:ojb7:serial:1352',
        ]);
        FitIshSessionZone::factory()->create([
            'fit_ish_session_id' => $session->id,
            'name' => 'Very light / Recovery',
            'bpm_label' => '<145 BPM',
            'duration_label' => '25:36',
            'percentage_label' => '62%',
        ]);
        FitIshSessionGraphPoint::factory()->create([
            'fit_ish_session_id' => $session->id,
        ]);

        $response = $this->actingAs($user)
            ->getJson(route('fit-ish.days.show', ['date' => '2026-09-14']))
            ->assertOk();

        $html = (string) $response->json('html');

        $this->assertSame('2026-09-14', $response->json('date'));
        $this->assertStringContainsString('Heart rate', $html);
        $this->assertStringContainsString('Wyn', $html);
        $this->assertStringContainsString('Recovery', $html);
        $this->assertStringContainsString('40.0', $html);
        $this->assertStringNotContainsString('99.0', $html);
        $this->assertStringContainsString('Zone battle', $html);
        $this->assertStringContainsString('data-fit-ish-chart="radar"', $html);
    }

    public function test_session_day_escapes_player_names(): void
    {
        $this->travelTo('2026-09-15 12:00:00');

        $user = User::factory()->withFitIsh()->create([
            'name' => "<script>alert('xss')</script>",
        ]);
        FitIshSession::factory()->create([
            'user_id' => $user->id,
            'class_date' => '2026-09-14',
        ]);

        $html = $this->actingAs($user)
            ->getJson(route('fit-ish.days.show', ['date' => '2026-09-14']))
            ->assertOk()
            ->json('html');

        $this->assertStringNotContainsString("<script>alert('xss')</script>", (string) $html);
        $this->assertStringContainsString('alert', (string) $html);
    }
}
