<?php

namespace App\Services\PubGolf;

use App\Models\PubGolfDrinkLog;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class DescribePubGolfMapPins
{
    /**
     * @param  Collection<int, PubGolfDrinkLog>  $logs
     * @return list<array{lat: float, lng: float, name: string, color: string, initials: string, label: string, emoji: string, location: ?string, time: string, crawl: ?string, is_you: bool, user_id: int}>
     */
    public function fromLogs(Collection $logs, User $viewer, string $timezone, bool $includeLocations = true): array
    {
        return $logs
            ->filter(fn (PubGolfDrinkLog $log): bool => $this->hasCoordinates($log))
            ->sortBy('id')
            ->values()
            ->map(function (PubGolfDrinkLog $log) use ($viewer, $timezone, $includeLocations): array {
                $user = $log->user;
                $listed = $log->listed();

                return $this->pin(
                    $log,
                    $viewer,
                    $timezone,
                    $includeLocations,
                    $user?->name ?? 'Unknown',
                    $user?->boardColor() ?? User::fallbackColor($log->user_id),
                    $listed->label,
                    $listed->category->emoji(),
                );
            })
            ->all();
    }

    /**
     * @return array{lat: float, lng: float, name: string, color: string, initials: string, label: string, emoji: string, location: ?string, time: string, crawl: ?string, is_you: bool, user_id: int}
     */
    private function pin(
        PubGolfDrinkLog $log,
        User $viewer,
        string $timezone,
        bool $includeLocations,
        string $name,
        string $color,
        string $label,
        string $emoji,
    ): array {
        return [
            'lat' => (float) $log->latitude,
            'lng' => (float) $log->longitude,
            'name' => $name,
            'color' => $color,
            'initials' => $this->initials($name),
            'label' => $label,
            'emoji' => $emoji,
            'location' => $includeLocations && is_string($log->location) && $log->location !== '' ? $log->location : null,
            'time' => $log->created_at?->copy()->timezone($timezone)->format('H:i') ?? '',
            'crawl' => null,
            'is_you' => $log->user_id === $viewer->id,
            'user_id' => $log->user_id,
        ];
    }

    private function hasCoordinates(PubGolfDrinkLog $log): bool
    {
        return $log->latitude !== null && $log->longitude !== null;
    }

    private function initials(string $name): string
    {
        $name = trim($name);

        if ($name === '') {
            return '?';
        }

        $parts = preg_split('/\s+/', $name, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        if (count($parts) === 1) {
            return Str::upper(Str::substr($parts[0], 0, 2));
        }

        return Str::upper(Str::substr($parts[0], 0, 1).Str::substr($parts[1], 0, 1));
    }
}
