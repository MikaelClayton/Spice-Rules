<?php

namespace App\Services\PubGolf;

use App\Enums\PubGolfDrinkCategory;
use App\Models\PubGolfCustomDrink;
use Illuminate\Support\Collection;

final readonly class PubGolfListedDrink
{
    public const CUSTOM_PREFIX = 'custom:';

    public function __construct(
        public string $key,
        public string $label,
        public PubGolfDrinkCategory $category,
        public ?string $imageUrl,
        public float $standardDrinks,
        public bool $isCustom,
        public bool $isListed = true,
        public ?int $createdByUserId = null,
        public ?int $customId = null,
    ) {}

    public static function fromCustom(PubGolfCustomDrink $drink): self
    {
        return new self(
            key: (string) $drink->id,
            label: $drink->name,
            category: $drink->category,
            imageUrl: $drink->photoUrl(),
            standardDrinks: 1.0,
            isCustom: true,
            isListed: $drink->removed_at === null,
            createdByUserId: $drink->user_id,
            customId: $drink->id,
        );
    }

    /**
     * @param  Collection<int, PubGolfCustomDrink>|null  $customs
     */
    public static function fromKey(string $key, ?Collection $customs = null): ?self
    {
        $customId = self::customId($key);

        if ($customId === null) {
            return null;
        }

        $custom = $customs?->get($customId) ?? PubGolfCustomDrink::query()->find($customId);

        return $custom instanceof PubGolfCustomDrink ? self::fromCustom($custom) : null;
    }

    public static function customId(string $key): ?int
    {
        if (str_starts_with($key, self::CUSTOM_PREFIX)) {
            $id = (int) substr($key, strlen(self::CUSTOM_PREFIX));

            return $id > 0 ? $id : null;
        }

        if (! ctype_digit($key)) {
            return null;
        }

        $id = (int) $key;

        return $id > 0 ? $id : null;
    }

    /**
     * @return array<string, list<self>>
     */
    public static function grouped(): array
    {
        $groups = [];

        $customs = PubGolfCustomDrink::query()
            ->active()
            ->orderBy('name')
            ->orderBy('id')
            ->get();

        foreach ($customs as $custom) {
            $listed = self::fromCustom($custom);
            $groups[$listed->category->value][] = $listed;
        }

        return $groups;
    }
}
