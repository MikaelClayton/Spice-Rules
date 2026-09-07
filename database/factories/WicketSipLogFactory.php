<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\WicketGroup;
use App\Models\WicketSipLog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WicketSipLog>
 */
class WicketSipLogFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'wicket_group_id' => WicketGroup::factory(),
            'user_id' => User::factory(),
            'sips' => fake()->numberBetween(1, 4),
        ];
    }
}
