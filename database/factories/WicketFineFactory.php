<?php

namespace Database\Factories;

use App\Enums\WicketFineType;
use App\Models\User;
use App\Models\WicketFine;
use App\Models\WicketGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WicketFine>
 */
class WicketFineFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'wicket_group_id' => WicketGroup::factory(),
            'issued_by_user_id' => User::factory(),
            'issued_to_user_id' => User::factory(),
            'reason' => fake()->sentence(),
            'type' => WicketFineType::Sips,
            'sips_owed' => 2,
            'sips_completed' => 0,
            'completed_at' => null,
        ];
    }

    public function ofType(WicketFineType $type, int $sips = 2): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => $type,
            'sips_owed' => $type->isSip() ? $sips : 0,
        ]);
    }

    public function completed(): static
    {
        return $this->state(function (array $attributes): array {
            $owed = (int) ($attributes['sips_owed'] ?? 0);

            return [
                'sips_completed' => $owed,
                'completed_at' => now(),
            ];
        });
    }
}
