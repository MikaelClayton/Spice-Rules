<?php

namespace Tests\Feature;

use App\Models\FitIshSession;
use App\Models\FitIshStudio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Sleep;
use Tests\Fixtures\FitIshPayloads;
use Tests\TestCase;

class SyncFitIshCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_syncs_sessions_and_summaries_for_users_with_a_user_id(): void
    {
        $this->travelTo(Carbon::parse('2026-09-15 07:00:00', 'Africa/Johannesburg'));
        Storage::fake('public');

        $user = User::factory()->withFitIsh()->create();
        $studio = FitIshStudio::factory()->faerieGlen()->create();
        $user->fitIshStudios()->attach($studio);
        User::factory()->create();

        $sessionId = '2026-09-15_0600:studio:ojb7:serial:1352';

        Http::preventStrayRequests();
        Http::fake([
            'https://api.lionheart.f45.com/v3/profile/sessions/summary*' => Http::response(FitIshPayloads::summary()),
            'https://api.lionheart.f45.com/v3/profile/sessions*' => Http::response(FitIshPayloads::sessionList($sessionId)),
            'https://api.lionheart.f45.com/v3/sessions/*' => Http::response(FitIshPayloads::session()),
            'https://f45tv.cdn.f45.com/*' => Http::response('fake-png', 200, ['Content-Type' => 'image/png']),
        ]);

        $this->artisan('fit-ish:sync')
            ->expectsOutput('Synced 1 Fit-Ish profile(s).')
            ->assertSuccessful();

        $this->assertDatabaseHas('fit_ish_sessions', [
            'session_id' => $sessionId,
            'user_id' => $user->id,
            'points' => 48.30,
        ]);
        $this->assertDatabaseHas('fit_ish_workouts', [
            'name' => 'Phoenix',
            'type' => 'resistance',
        ]);
        $this->assertDatabaseHas('fit_ish_session_zones', [
            'zone_number' => 1,
            'name' => 'Very light / Recovery',
        ]);
        $this->assertDatabaseHas('fit_ish_session_graph_points', [
            'minute' => 4,
            'type' => 'recordedBpm',
            'bpm_max' => 119,
        ]);
        $this->assertDatabaseHas('fit_ish_profile_summaries', [
            'user_id' => $user->id,
            'timeframe_key' => 'allTime',
            'session_count' => 1,
        ]);
        $this->assertDatabaseHas('outgoing_api_calls', [
            'source' => 'fit-ish_sync',
            'url' => 'https://api.lionheart.f45.com/v3/profile/sessions/summary?user_id=13138221',
            'succeeded' => true,
        ]);
        $this->assertDatabaseHas('outgoing_api_calls', [
            'source' => 'fit-ish_sync',
            'url' => 'https://api.lionheart.f45.com/v3/sessions/'.$sessionId.'?user_id=13138221',
            'succeeded' => true,
        ]);

        $workout = FitIshSession::query()->first()?->workout;
        $this->assertNotNull($workout?->logo_path);
        Storage::disk('public')->assertExists($workout->logo_path);
    }

    public function test_it_skips_users_without_a_user_id(): void
    {
        $this->travelTo(Carbon::parse('2026-09-15 07:00:00', 'Africa/Johannesburg'));

        User::factory()->create();

        Http::preventStrayRequests();
        Http::fake();

        $this->artisan('fit-ish:sync')
            ->expectsOutput('Synced 0 Fit-Ish profile(s).')
            ->assertSuccessful();

        Http::assertNothingSent();
        $this->assertDatabaseCount('outgoing_api_calls', 0);
        $this->assertDatabaseCount('fit_ish_sessions', 0);
    }

    public function test_missing_sessions_are_ignored(): void
    {
        $this->travelTo(Carbon::parse('2026-09-15 07:00:00', 'Africa/Johannesburg'));

        $user = User::factory()->withFitIsh()->create();
        $studio = FitIshStudio::factory()->faerieGlen()->create();
        $user->fitIshStudios()->attach($studio);

        Http::preventStrayRequests();
        Http::fake([
            'https://api.lionheart.f45.com/v3/profile/sessions/summary*' => Http::response(FitIshPayloads::summary()),
            'https://api.lionheart.f45.com/v3/profile/sessions*' => Http::response(FitIshPayloads::sessionList()),
            'https://api.lionheart.f45.com/v3/sessions/*' => Http::response(['success' => false], 404),
        ]);

        $this->artisan('fit-ish:sync')->assertSuccessful();

        $this->assertDatabaseCount('fit_ish_sessions', 0);
        $this->assertDatabaseHas('fit_ish_profile_summaries', [
            'user_id' => $user->id,
            'timeframe_key' => 'week',
        ]);
    }

    public function test_users_can_sync_from_their_profile(): void
    {
        $this->travelTo(Carbon::parse('2026-09-15 06:30:00', 'Africa/Johannesburg'));
        Storage::fake('public');
        $user = User::factory()->withFitIsh()->create();

        Http::preventStrayRequests();
        Http::fake([
            'https://api.lionheart.f45.com/v3/profile/sessions/summary*' => Http::response(FitIshPayloads::summary()),
            'https://api.lionheart.f45.com/v3/profile/sessions*' => Http::response(FitIshPayloads::sessionList()),
            'https://api.lionheart.f45.com/v3/sessions/*' => Http::response(FitIshPayloads::session()),
            'https://f45tv.cdn.f45.com/*' => Http::response('fake-png', 200, ['Content-Type' => 'image/png']),
        ]);

        $this->actingAs($user)
            ->post(route('profile.fit-ish.sync'))
            ->assertRedirect(route('profile.edit', ['tab' => 'fit-ish']))
            ->assertSessionHas('status');

        $this->assertDatabaseHas('fit_ish_sessions', [
            'user_id' => $user->id,
            'session_id' => '2026-09-15_0600:studio:ojb7:serial:1352',
        ]);
    }

    public function test_scheduled_sync_does_not_call_lionheart_before_seven_sast(): void
    {
        $this->travelTo(Carbon::parse('2026-09-15 06:59:00', 'Africa/Johannesburg'));
        User::factory()->withFitIsh()->create();

        Http::preventStrayRequests();
        Http::fake();

        $this->artisan('fit-ish:sync')
            ->expectsOutput('Fit-Ish sync waits until 07:00 SAST.')
            ->assertSuccessful();

        Http::assertNothingSent();
        $this->assertDatabaseCount('outgoing_api_calls', 0);
        $this->assertDatabaseCount('cron_runs', 0);
    }

    public function test_scheduled_sync_stops_after_todays_data_is_fetched(): void
    {
        $this->travelTo(Carbon::parse('2026-09-15 07:00:00', 'Africa/Johannesburg'));
        Storage::fake('public');
        $user = User::factory()->withFitIsh()->create();
        $studio = FitIshStudio::factory()->faerieGlen()->create();
        $user->fitIshStudios()->attach($studio);

        Http::preventStrayRequests();
        Http::fake([
            'https://api.lionheart.f45.com/v3/profile/sessions/summary*' => Http::response(FitIshPayloads::summary()),
            'https://api.lionheart.f45.com/v3/profile/sessions*' => Http::response(FitIshPayloads::sessionList()),
            'https://api.lionheart.f45.com/v3/sessions/*' => Http::response(FitIshPayloads::session()),
            'https://f45tv.cdn.f45.com/*' => Http::response('fake-png', 200, ['Content-Type' => 'image/png']),
        ]);

        $this->artisan('fit-ish:sync')->assertSuccessful();

        $sent = Http::recorded()->count();
        $this->assertGreaterThan(0, $sent);

        $this->artisan('fit-ish:sync')
            ->expectsOutput("Today's Fit-Ish data is already saved.")
            ->assertSuccessful();

        Http::assertSentCount($sent);
    }

    public function test_force_syncs_before_seven_sast(): void
    {
        $this->travelTo(Carbon::parse('2026-09-15 06:30:00', 'Africa/Johannesburg'));
        Storage::fake('public');
        $user = User::factory()->withFitIsh()->create();

        Http::preventStrayRequests();
        Http::fake([
            'https://api.lionheart.f45.com/v3/profile/sessions/summary*' => Http::response(FitIshPayloads::summary()),
            'https://api.lionheart.f45.com/v3/profile/sessions*' => Http::response(FitIshPayloads::sessionList()),
            'https://api.lionheart.f45.com/v3/sessions/*' => Http::response(FitIshPayloads::session()),
            'https://f45tv.cdn.f45.com/*' => Http::response('fake-png', 200, ['Content-Type' => 'image/png']),
        ]);

        $this->artisan('fit-ish:sync', ['--force' => true])
            ->expectsOutput('Synced 1 Fit-Ish profile(s).')
            ->assertSuccessful();

        $this->assertDatabaseHas('fit_ish_sessions', [
            'user_id' => $user->id,
            'session_id' => '2026-09-15_0600:studio:ojb7:serial:1352',
        ]);
    }

    public function test_failed_scheduled_sync_is_retried_after_seven_sast(): void
    {
        $this->travelTo(Carbon::parse('2026-09-15 07:30:00', 'Africa/Johannesburg'));
        Sleep::fake();
        User::factory()->withFitIsh()->create();

        Http::preventStrayRequests();
        Http::fake([
            'https://api.lionheart.f45.com/*' => Http::response('nope', 500),
        ]);

        $this->artisan('fit-ish:sync')->assertSuccessful();

        $sent = Http::recorded()->count();
        $this->assertGreaterThan(0, $sent);

        $this->artisan('fit-ish:sync')->assertSuccessful();

        $this->assertGreaterThan($sent, Http::recorded()->count());
    }
}
