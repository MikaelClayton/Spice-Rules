<?php

namespace Tests\Unit\Enums;

use App\Enums\PubGolfDrink;
use App\Enums\PubGolfDrinkCategory;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

class PubGolfDrinkTest extends TestCase
{
    #[TestWith([PubGolfDrink::CarlingBlackLabel, 'Black Label', PubGolfDrinkCategory::Beer])]
    #[TestWith([PubGolfDrink::SavannaDry, 'Savanna Dry', PubGolfDrinkCategory::Cider])]
    #[TestWith([PubGolfDrink::BrutalFruit, 'Brutal Fruit', PubGolfDrinkCategory::Rtd])]
    #[TestWith([PubGolfDrink::KlippiesAndCoke, 'Klippies & Coke', PubGolfDrinkCategory::Spirit])]
    #[TestWith([PubGolfDrink::FourthStreet, '4th Street', PubGolfDrinkCategory::Wine])]
    #[TestWith([PubGolfDrink::Springbokkie, 'Springbokkie', PubGolfDrinkCategory::Shooter])]
    public function test_south_african_drinks_keep_their_labels_and_categories(
        PubGolfDrink $drink,
        string $label,
        PubGolfDrinkCategory $category,
    ): void {
        $this->assertSame($label, $drink->label());
        $this->assertSame($category, $drink->category());
    }

    public function test_the_catalog_has_no_soft_drinks(): void
    {
        $values = array_map(fn (PubGolfDrink $drink): string => $drink->value, PubGolfDrink::cases());
        $categories = array_map(fn (PubGolfDrink $drink): string => $drink->category()->value, PubGolfDrink::cases());

        $this->assertNotContains('water', $values);
        $this->assertNotContains('soft', $categories);
        $this->assertSame(PubGolfDrinkCategory::cases(), [
            PubGolfDrinkCategory::Beer,
            PubGolfDrinkCategory::Cider,
            PubGolfDrinkCategory::Rtd,
            PubGolfDrinkCategory::Spirit,
            PubGolfDrinkCategory::Wine,
            PubGolfDrinkCategory::Shooter,
        ]);
    }
}
