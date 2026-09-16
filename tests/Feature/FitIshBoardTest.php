<?php

namespace Tests\Feature;

use App\Models\FitIshProfileSummary;
use App\Models\FitIshSession;
use App\Models\FitIshSessionGraphPoint;
use App\Models\FitIshSessionZone;
use App\Models\FitIshStudio;
use App\Models\FitIshWorkout;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FitIshBoardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_from_fit_ish(): void
    {
        $this->get(route('fit-ish.index'))->assertRedirect(route('login'));
    }

    public function test_weekly_tab_shows_standings(): void
    {
        $user = User::factory()->withFitIsh()->create(['name' => 'Wyn']);
        $studio = FitIshStudio::factory()->faerieGlen()->create();
        FitIshSession::factory()->create([
            'user_id' => $user->id,
            'fit_ish_studio_id' => $studio->id,
            'class_date' => today()->toDateString(),
            'points' => 51.2,
            'session_id' => today()->toDateString().'_0600:studio:ojb7:serial:1352',
        ]);

        $this->actingAs($user)
            ->get(route('fit-ish.index', ['tab' => 'weekly']))
            ->assertOk()
            ->assertSee('Standings')
            ->assertSee('Wyn')
            ->assertSee('51.2');
    }

    public function test_today_tab_shows_heart_rate_zones_with_legends(): void
    {
        $user = User::factory()->withFitIsh()->create();
        $studio = FitIshStudio::factory()->faerieGlen()->create();
        $session = FitIshSession::factory()->create([
            'user_id' => $user->id,
            'fit_ish_studio_id' => $studio->id,
            'class_date' => today()->toDateString(),
            'session_id' => today()->toDateString().'_0600:studio:ojb7:serial:1352',
        ]);
        FitIshSessionZone::factory()->create([
            'fit_ish_session_id' => $session->id,
            'zone_number' => 1,
            'name' => 'Very light / Recovery',
            'color_hex' => '#326EC8',
            'bpm_label' => '<145 BPM',
            'duration_label' => '25:36',
            'percentage_label' => '62%',
        ]);
        FitIshSessionZone::factory()->create([
            'fit_ish_session_id' => $session->id,
            'zone_number' => 4,
            'name' => 'High',
            'color_hex' => '#F2911B',
            'min_bpm' => 168,
            'max_bpm' => 178,
            'bpm_label' => '168 - 178 BPM',
            'duration_seconds' => 101,
            'duration_label' => '01:41',
            'percentage_value' => 4.1,
            'percentage_label' => '4%',
        ]);
        FitIshSessionGraphPoint::factory()->withoutRecording()->create([
            'fit_ish_session_id' => $session->id,
            'minute' => 1,
        ]);
        FitIshSessionGraphPoint::factory()->create([
            'fit_ish_session_id' => $session->id,
            'minute' => 4,
            'bpm_min' => 100,
            'bpm_max' => 119,
        ]);
        FitIshSessionGraphPoint::factory()->create([
            'fit_ish_session_id' => $session->id,
            'minute' => 41,
            'bpm_min' => 147,
            'bpm_max' => 173,
        ]);

        $this->actingAs($user)
            ->get(route('fit-ish.index'))
            ->assertOk()
            ->assertSee('Heart rate')
            ->assertSee('Recovery')
            ->assertSee('<145 BPM')
            ->assertSee('25:36')
            ->assertSee('62%')
            ->assertSee('High')
            ->assertSee('168 - 178 BPM')
            ->assertSee('01:41')
            ->assertSee('avg 133');
    }

    public function test_workout_logos_use_the_current_host_and_player_colour(): void
    {
        config([
            'app.url' => 'https://spice-rules.example',
            'filesystems.disks.public.url' => 'https://spice-rules.example/storage',
        ]);

        $user = User::factory()->withFitIsh()->create([
            'name' => 'Wyn',
            'color' => '#2A9D8F',
        ]);
        $workout = FitIshWorkout::factory()->phoenix()->create([
            'logo_path' => 'fit-ish/workouts/phoenix.png',
            'logo_url' => 'https://f45tv.cdn.f45.com/lionheart-logos/F45_Logos_Phoenix_600x600.png',
        ]);
        FitIshSession::factory()->create([
            'user_id' => $user->id,
            'fit_ish_workout_id' => $workout->id,
            'class_date' => today()->toDateString(),
        ]);

        $this->actingAs($user)
            ->get(route('fit-ish.index'))
            ->assertSee('/storage/fit-ish/workouts/phoenix.png', false)
            ->assertSee('data-workout-logo', false)
            ->assertSee('background: #2A9D8F', false)
            ->assertDontSee('backdrop-blur-sm', false)
            ->assertDontSee('https://spice-rules.example/storage', false)
            ->assertDontSee('https://f45tv.cdn.f45.com', false);
    }

    public function test_you_tab_shows_profile_summaries(): void
    {
        $user = User::factory()->withFitIsh()->create();
        FitIshProfileSummary::factory()->create([
            'user_id' => $user->id,
            'timeframe_key' => 'allTime',
            'timeframe_name' => 'All Time',
            'average_points' => 48.3,
        ]);

        $this->actingAs($user)
            ->get(route('fit-ish.index', ['tab' => 'you']))
            ->assertOk()
            ->assertSee('All Time')
            ->assertSee('48.3');
    }

    public function test_today_tab_does_not_render_a_past_session_day(): void
    {
        $this->travelTo('2026-09-15 12:00:00');

        $user = User::factory()->withFitIsh()->create();
        FitIshSession::factory()->create([
            'user_id' => $user->id,
            'class_date' => '2026-09-14',
            'points' => 40.0,
            'session_id' => '2026-09-14_0600:studio:ojb7:serial:1352',
        ]);

        $this->actingAs($user)
            ->get(route('fit-ish.index'))
            ->assertOk()
            ->assertSee('data-session-date="2026-09-14"', false)
            ->assertDontSee('data-loaded-date="2026-09-14"', false);
    }

    public function test_sessions_tab_lists_synced_classes_by_date(): void
    {
        $this->travelTo('2026-09-15 12:00:00');

        $user = User::factory()->withFitIsh()->create(['name' => 'Wyn']);
        $studio = FitIshStudio::factory()->faerieGlen()->create();
        $workout = FitIshWorkout::factory()->phoenix()->create();
        $session = FitIshSession::factory()->create([
            'user_id' => $user->id,
            'fit_ish_studio_id' => $studio->id,
            'fit_ish_workout_id' => $workout->id,
            'class_date' => '2026-09-14',
            'class_time' => '06:00:00',
            'points' => 40.0,
            'session_id' => '2026-09-14_0600:studio:ojb7:serial:1352',
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

        $this->actingAs($user)
            ->get(route('fit-ish.index', ['tab' => 'sessions']))
            ->assertOk()
            ->assertSee('Daily')
            ->assertSee('Sep 14, 2026')
            ->assertSee('data-fit-ish-board', false)
            ->assertSee('data-session-date="2026-09-14"', false)
            ->assertSee('Heart rate')
            ->assertSee('Wyn')
            ->assertSee('PHOENIX')
            ->assertSee('F45 Faerie Glen')
            ->assertSee('40.0');
    }

    public function test_workouts_tab_lists_synced_workouts_with_class_stats(): void
    {
        $this->travelTo('2026-09-15 12:00:00');

        $user = User::factory()->withFitIsh()->create();
        $workout = FitIshWorkout::factory()->phoenix()->create([
            'description' => 'A resistance burner.',
        ]);
        FitIshSession::factory()->create([
            'user_id' => $user->id,
            'fit_ish_workout_id' => $workout->id,
            'class_date' => '2026-09-15',
            'points' => 48.3,
        ]);

        $this->actingAs($user)
            ->get(route('fit-ish.index', ['tab' => 'workouts']))
            ->assertOk()
            ->assertSee('Workouts')
            ->assertSee('PHOENIX')
            ->assertSee('resistance')
            ->assertSee('A resistance burner.')
            ->assertSee('48.3');
    }

    public function test_workouts_tab_escapes_workout_names(): void
    {
        $user = User::factory()->withFitIsh()->create();
        FitIshWorkout::factory()->create([
            'name' => 'Alert',
            'display_name' => "<script>alert('xss')</script>",
            'type' => 'cardio',
        ]);

        $this->actingAs($user)
            ->get(route('fit-ish.index', ['tab' => 'workouts']))
            ->assertOk()
            ->assertDontSee("<script>alert('xss')</script>", false)
            ->assertSee('alert');
    }
}
