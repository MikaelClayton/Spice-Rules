<?php

namespace Tests\Unit\Services\FitIsh;

use App\Models\FitIshWorkout;
use App\Models\OutgoingApiCall;
use App\Services\FitIsh\StoreFitIshWorkoutLogo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StoreFitIshWorkoutLogoTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_redownloads_when_the_saved_file_is_missing(): void
    {
        Storage::fake('public');
        Http::preventStrayRequests();
        Http::fake([
            'https://cdn.example/logo.png' => Http::response('png-bytes', 200, ['Content-Type' => 'image/png']),
        ]);

        $workout = FitIshWorkout::factory()->create([
            'logo_url' => 'https://cdn.example/logo.png',
            'logo_path' => 'fit-ish/workouts/missing.png',
        ]);

        app(StoreFitIshWorkoutLogo::class)->handle($workout, 'https://cdn.example/logo.png');

        Storage::disk('public')->assertExists('fit-ish/workouts/'.$workout->id.'.png');
        $this->assertSame('fit-ish/workouts/'.$workout->id.'.png', $workout->fresh()->logo_path);
        Http::assertSentCount(1);
    }

    public function test_it_does_not_save_a_path_when_the_file_cannot_be_written(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://cdn.example/logo.png' => Http::response('png-bytes', 200, ['Content-Type' => 'image/png']),
        ]);

        $root = storage_path('framework/testing/disks/fit-ish-readonly');
        File::deleteDirectory($root);
        File::ensureDirectoryExists($root);
        chmod($root, 0555);
        config(['filesystems.disks.public.root' => $root]);
        Storage::forgetDisk('public');

        $workout = FitIshWorkout::factory()->create([
            'logo_url' => null,
            'logo_path' => null,
        ]);

        try {
            app(StoreFitIshWorkoutLogo::class)->handle($workout, 'https://cdn.example/logo.png');
        } finally {
            chmod($root, 0755);
            File::deleteDirectory($root);
        }

        $this->assertNull($workout->fresh()->logo_path);
        $this->assertDatabaseHas('outgoing_api_calls', [
            'source' => 'fit-ish',
            'url' => 'https://cdn.example/logo.png',
            'succeeded' => false,
            'error_message' => 'Logo storage write failed.',
        ]);
        $this->assertSame(0, OutgoingApiCall::query()->where('succeeded', true)->count());
    }
}
