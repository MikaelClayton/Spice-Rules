<?php

namespace App\Models;

use App\Enums\PubGolfDrinkCategory;
use App\Services\PubGolf\PubGolfListedDrink;
use Database\Factories\PubGolfDrinkLogFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['pub_golf_crawl_id', 'user_id', 'drink_id'])]
class PubGolfDrinkLog extends Model
{
    /** @use HasFactory<PubGolfDrinkLogFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<PubGolfCrawl, $this>
     */
    public function crawl(): BelongsTo
    {
        return $this->belongsTo(PubGolfCrawl::class, 'pub_golf_crawl_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<PubGolfCustomDrink, $this>
     */
    public function drink(): BelongsTo
    {
        return $this->belongsTo(PubGolfCustomDrink::class, 'drink_id');
    }

    public function listed(): PubGolfListedDrink
    {
        $drink = $this->drink;

        if ($drink instanceof PubGolfCustomDrink) {
            return PubGolfListedDrink::fromCustom($drink);
        }

        return new PubGolfListedDrink(
            key: (string) $this->drink_id,
            label: 'Unknown drink',
            category: PubGolfDrinkCategory::Beer,
            imageUrl: null,
            standardDrinks: 1.0,
            isCustom: false,
        );
    }
}
