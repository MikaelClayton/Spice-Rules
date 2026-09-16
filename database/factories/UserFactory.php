<?php

namespace Database\Factories;

use App\Models\Geoguesser;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'allow_pub_golf_location' => false,
            'fit_ish_user_id' => null,
            'fit_ish_serial' => null,
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    public function sharingPubGolfLocation(): static
    {
        return $this->state(fn (array $attributes): array => [
            'allow_pub_golf_location' => true,
        ]);
    }

    public function withFitIsh(string $userId = '13138221', string $serial = '1352'): static
    {
        return $this->state(fn (array $attributes): array => [
            'fit_ish_user_id' => $userId,
            'fit_ish_serial' => $serial,
        ]);
    }

    public function withActiveGeoguessr(): static
    {
        return $this->has(Geoguesser::factory()->state([
            'is_active' => true,
            'ncfa' => 'test-ncfa',
        ]));
    }
}
