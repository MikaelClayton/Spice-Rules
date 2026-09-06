<?php

namespace Tests\Unit\Services\Geoguessr;

use App\Services\Geoguessr\ContinentForCountry;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

class ContinentForCountryTest extends TestCase
{
    #[TestWith(['jp', 'Asia'])]
    #[TestWith(['PE', 'South America'])]
    #[TestWith(['uk', 'Europe'])]
    #[TestWith(['US', 'North America'])]
    #[TestWith(['ZA', 'Africa'])]
    #[TestWith(['NZ', 'Oceania'])]
    public function test_maps_country_codes_to_continents(string $code, string $continent): void
    {
        $this->assertSame($continent, ContinentForCountry::name($code));
    }

    #[TestWith(['XX'])]
    #[TestWith([null])]
    #[TestWith([''])]
    public function test_returns_null_for_unknown_country_codes(?string $code): void
    {
        $this->assertSame(null, ContinentForCountry::name($code));
    }
}
