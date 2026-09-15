<?php

namespace Tests\Unit\Services\PubGolf;

use App\Enums\PubGolfDrinkCategory;
use App\Models\PubGolfCustomDrink;
use App\Models\PubGolfDrinkLog;
use App\Models\User;
use App\Services\PubGolf\DescribePubGolfMapPins;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Tests\TestCase;

class DescribePubGolfMapPinsTest extends TestCase
{
    public function test_logs_without_both_coordinates_are_omitted(): void
    {
        $host = $this->player('Host', '#D82820', 1);
        $friend = $this->player('Alex Friend', '#2A9D8F', 2);
        $drink = $this->drink('Castle Lager');

        $pins = (new DescribePubGolfMapPins)->fromLogs(Collection::make([
            $this->log($host, $drink, -25.6828855, 28.2704473, 'Oppie Stoep', '2026-09-14 16:00:00'),
            $this->log($host, $drink, -25.7, null, 'Nowhere', '2026-09-14 16:10:00'),
            $this->log($friend, $drink, -25.746, 28.188, 'The Aroma', '2026-09-14 16:20:00'),
        ]), $host, (string) config('app.timezone'));

        $this->assertSame([
            [
                'lat' => -25.6828855,
                'lng' => 28.2704473,
                'name' => 'Host',
                'color' => '#D82820',
                'initials' => 'HO',
                'label' => 'Castle Lager',
                'emoji' => '🍺',
                'location' => 'Oppie Stoep',
                'time' => '16:00',
                'crawl' => null,
                'is_you' => true,
                'user_id' => 1,
            ],
            [
                'lat' => -25.746,
                'lng' => 28.188,
                'name' => 'Alex Friend',
                'color' => '#2A9D8F',
                'initials' => 'AF',
                'label' => 'Castle Lager',
                'emoji' => '🍺',
                'location' => 'The Aroma',
                'time' => '16:20',
                'crawl' => null,
                'is_you' => false,
                'user_id' => 2,
            ],
        ], $pins);
    }

    public function test_venue_names_can_be_hidden_while_pins_remain(): void
    {
        $host = $this->player('Host', '#D82820', 1);
        $drink = $this->drink('Castle Lager');

        $pins = (new DescribePubGolfMapPins)->fromLogs(Collection::make([
            $this->log($host, $drink, -25.6828855, 28.2704473, 'Oppie Stoep', '2026-09-14 16:00:00'),
        ]), $host, (string) config('app.timezone'), includeLocations: false);

        $this->assertSame(null, $pins[0]['location']);
        $this->assertSame(-25.6828855, $pins[0]['lat']);
    }

    private function player(string $name, string $color, int $id): User
    {
        $user = new User([
            'name' => $name,
            'color' => $color,
        ]);
        $user->id = $id;

        return $user;
    }

    private function drink(string $name): PubGolfCustomDrink
    {
        $drink = new PubGolfCustomDrink([
            'name' => $name,
            'category' => PubGolfDrinkCategory::Beer,
        ]);
        $drink->id = 1;

        return $drink;
    }

    private function log(
        User $user,
        PubGolfCustomDrink $drink,
        ?float $latitude,
        ?float $longitude,
        ?string $location,
        string $time,
    ): PubGolfDrinkLog {
        $log = new PubGolfDrinkLog;
        $log->user_id = $user->id;
        $log->latitude = $latitude;
        $log->longitude = $longitude;
        $log->location = $location;
        $log->created_at = Carbon::parse($time);
        $log->setRelation('user', $user);
        $log->setRelation('drink', $drink);

        return $log;
    }
}
