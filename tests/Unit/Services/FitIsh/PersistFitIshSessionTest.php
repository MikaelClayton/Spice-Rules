<?php

namespace Tests\Unit\Services\FitIsh;

use App\Models\User;
use App\Services\FitIsh\PersistFitIshSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\Fixtures\FitIshPayloads;
use Tests\TestCase;

class PersistFitIshSessionTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_the_workout_studio_zones_and_graph(): void
    {
        Storage::fake('public');
        Http::preventStrayRequests();
        Http::fake([
            'https://f45tv.cdn.f45.com/*' => Http::response('fake-png', 200, ['Content-Type' => 'image/png']),
        ]);

        $user = User::factory()->withFitIsh()->create(['fit_ish_serial' => null]);

        $session = app(PersistFitIshSession::class)->handle($user, FitIshPayloads::session());

        $this->assertNotNull($session);
        $this->assertSame('2026-09-15_0600:studio:ojb7:serial:1352', $session->session_id);
        $this->assertSame('Phoenix', $session->workout?->name);
        $this->assertSame('resistance', $session->workout?->type);
        $this->assertSame('ojb7', $session->studio?->code);
        $this->assertSame(5061, $session->studio?->external_id);
        $this->assertSame(76, $session->resting_hr_value);
        $this->assertCount(2, $session->zones);
        $this->assertCount(3, $session->graphPoints);
        $this->assertSame('1352', $user->fresh()->fit_ish_serial);
        $this->assertTrue($user->fresh()->fitIshStudios->contains($session->studio));
        Storage::disk('public')->assertExists($session->workout?->logo_path);
    }

    public function test_it_updates_an_existing_session_instead_of_duplicating(): void
    {
        Storage::fake('public');
        Http::preventStrayRequests();
        Http::fake([
            'https://f45tv.cdn.f45.com/*' => Http::response('fake-png', 200, ['Content-Type' => 'image/png']),
        ]);

        $user = User::factory()->withFitIsh()->create();
        $persist = app(PersistFitIshSession::class);
        $persist->handle($user, FitIshPayloads::session());
        $persist->handle($user, FitIshPayloads::session([
            'summary' => ['points' => 50.1],
        ]));

        $this->assertDatabaseCount('fit_ish_sessions', 1);
        $this->assertDatabaseCount('fit_ish_workouts', 1);
        $this->assertDatabaseHas('fit_ish_sessions', [
            'session_id' => '2026-09-15_0600:studio:ojb7:serial:1352',
            'points' => 50.10,
        ]);
        $this->assertDatabaseCount('fit_ish_session_zones', 2);
    }
}
