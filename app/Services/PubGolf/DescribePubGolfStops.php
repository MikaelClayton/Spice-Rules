<?php

namespace App\Services\PubGolf;

use App\Models\PubGolfCrawl;
use App\Models\PubGolfDrinkLog;
use App\Models\PubGolfParticipant;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class DescribePubGolfStops
{
    public function crawlSharesLocations(PubGolfCrawl $crawl): bool
    {
        return $crawl->participants->contains(
            fn (PubGolfParticipant $participant): bool => $participant->user?->allowsPubGolfLocation() ?? false,
        );
    }

    /**
     * @param  Collection<int, PubGolfDrinkLog>  $logs
     * @return list<array{location: string, drink_count: int, drink_label: string, when: string}>
     */
    public function handle(Collection $logs, string $timezone): array
    {
        $stops = [];
        $breakStreak = false;

        foreach ($logs as $log) {
            $location = is_string($log->location) && $log->location !== '' ? $log->location : null;

            if ($location === null) {
                $breakStreak = true;

                continue;
            }

            $time = $log->created_at?->copy()->timezone($timezone)->format('H:i') ?? '';
            $lastKey = array_key_last($stops);

            if (
                $lastKey !== null
                && ! $breakStreak
                && Str::lower($stops[$lastKey]['location']) === Str::lower($location)
            ) {
                $stops[$lastKey]['drink_count']++;
                $stops[$lastKey]['drink_label'] = $this->drinkLabel($stops[$lastKey]['drink_count']);
                $stops[$lastKey]['when'] = $this->when($stops[$lastKey]['started_at'], $time);

                continue;
            }

            $stops[] = [
                'location' => $location,
                'drink_count' => 1,
                'drink_label' => $this->drinkLabel(1),
                'started_at' => $time,
                'when' => $this->when($time, $time),
            ];
            $breakStreak = false;
        }

        return array_map(function (array $stop): array {
            unset($stop['started_at']);

            return $stop;
        }, $stops);
    }

    private function drinkLabel(int $count): string
    {
        return $count === 1 ? '1 drink' : $count.' drinks';
    }

    private function when(string $firstAt, string $lastAt): string
    {
        return $firstAt === $lastAt ? $firstAt : $firstAt.'–'.$lastAt;
    }
}
