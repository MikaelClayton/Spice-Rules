<?php

namespace App\Services\PubGolf;

class DescribePubGolfCalorieBurn
{
    /**
     * @return array{
     *     calories: int,
     *     minutes: int,
     *     kcal_per_minute: float,
     *     met: float,
     *     weight_kg: int,
     *     equation: string,
     *     summary: string
     * }|null
     */
    public function handle(int $calories, ?int $weightKg = null): ?array
    {
        if ($calories < 1) {
            return null;
        }

        $weightKg ??= (int) config('pub-golf.calorie_burn.reference_weight_kg');
        $met = (float) config('pub-golf.calorie_burn.zone_2_met');
        $kcalPerMinute = round(($met * $weightKg) / 60, 2);
        $minutes = (int) max(1, (int) round($calories / $kcalPerMinute));
        $equation = sprintf(
            '%s ÷ (%s × %d ÷ 60) = %s ÷ %s ≈ %d minutes',
            number_format($calories),
            rtrim(rtrim(number_format($met, 1), '0'), '.'),
            $weightKg,
            number_format($calories),
            number_format($kcalPerMinute, 2),
            $minutes,
        );

        return [
            'calories' => $calories,
            'minutes' => $minutes,
            'kcal_per_minute' => $kcalPerMinute,
            'met' => $met,
            'weight_kg' => $weightKg,
            'equation' => $equation,
            'summary' => sprintf(
                'About %s minutes of zone 2 running at %d kg (7 MET).',
                number_format($minutes),
                $weightKg,
            ),
        ];
    }
}
