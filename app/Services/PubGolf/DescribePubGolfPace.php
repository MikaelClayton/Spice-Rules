<?php

namespace App\Services\PubGolf;

class DescribePubGolfPace
{
    /**
     * @var list<string>
     */
    private const STEPS = [
        'Warming up',
        'Sipping',
        'Steady',
        'Moving',
        'On one',
        'Legendary',
    ];

    /**
     * @return array{
     *     drinks_per_hour: float,
     *     label: string,
     *     level: int,
     *     tone: string,
     *     steps: list<string>,
     *     duration_seconds: int,
     *     duration_label: string
     * }
     */
    public function snapshot(int $alcoholicCount, int $durationSeconds): array
    {
        $seconds = max(0, $durationSeconds);
        $hours = max($seconds / 3600, 1 / 60);
        $drinksPerHour = round($alcoholicCount / $hours, 1);
        $level = $this->level($alcoholicCount, $drinksPerHour);

        return [
            'drinks_per_hour' => $drinksPerHour,
            'label' => self::STEPS[$level],
            'level' => $level,
            'tone' => $this->tone($level),
            'steps' => self::STEPS,
            'duration_seconds' => $seconds,
            'duration_label' => $this->durationLabel($seconds),
        ];
    }

    public function label(int $alcoholicCount, float $drinksPerHour): string
    {
        return self::STEPS[$this->level($alcoholicCount, $drinksPerHour)];
    }

    public function durationLabel(int $seconds): string
    {
        if ($seconds < 60) {
            return $seconds.'s';
        }

        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);

        if ($hours > 0) {
            return $hours.'h '.$minutes.'m';
        }

        return $minutes.'m';
    }

    private function level(int $alcoholicCount, float $drinksPerHour): int
    {
        return match (true) {
            $alcoholicCount === 0 => 0,
            $drinksPerHour < 1.0 => 1,
            $drinksPerHour < 2.0 => 2,
            $drinksPerHour < 3.5 => 3,
            $drinksPerHour < 5.0 => 4,
            default => 5,
        };
    }

    private function tone(int $level): string
    {
        return match ($level) {
            1 => 'info',
            2 => 'success',
            3 => 'warning',
            4 => 'secondary',
            5 => 'primary',
            default => 'neutral',
        };
    }
}
