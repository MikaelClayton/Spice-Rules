<?php

namespace Database\Factories;

use App\Models\PubGolfCrawl;
use App\Models\PubGolfCustomDrink;
use App\Models\PubGolfDrinkLog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PubGolfDrinkLog>
 */
class PubGolfDrinkLogFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'pub_golf_crawl_id' => PubGolfCrawl::factory(),
            'user_id' => fn (array $attributes) => PubGolfCrawl::query()
                ->findOrFail($attributes['pub_golf_crawl_id'])
                ->user_id,
            'drink_id' => PubGolfCustomDrink::factory(),
        ];
    }
}
