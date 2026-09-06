<?php

namespace Tests\Feature;

use App\Models\DeviceToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeviceTokenTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_save_a_device_token(): void
    {
        $this->postJson(route('profile.device-tokens.store'), [
            'token' => str_repeat('a', 40),
        ])->assertUnauthorized();
    }

    public function test_users_can_save_a_device_token(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson(route('profile.device-tokens.store'), [
                'token' => str_repeat('a', 40),
            ])
            ->assertOk()
            ->assertJson(['saved' => true]);

        $this->assertDatabaseHas('device_tokens', [
            'user_id' => $user->id,
            'token' => str_repeat('a', 40),
        ]);
    }

    public function test_saving_an_existing_token_moves_it_to_the_current_user(): void
    {
        $previous = User::factory()->create();
        $current = User::factory()->create();
        $token = str_repeat('b', 40);

        DeviceToken::factory()->create([
            'user_id' => $previous->id,
            'token' => $token,
        ]);

        $this->actingAs($current)
            ->postJson(route('profile.device-tokens.store'), [
                'token' => $token,
            ])
            ->assertOk();

        $this->assertDatabaseHas('device_tokens', [
            'user_id' => $current->id,
            'token' => $token,
        ]);
        $this->assertDatabaseCount('device_tokens', 1);
    }

    public function test_a_missing_token_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson(route('profile.device-tokens.store'), [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['token']);
    }

    public function test_a_short_token_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson(route('profile.device-tokens.store'), [
                'token' => str_repeat('a', 31),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['token']);
    }
}
