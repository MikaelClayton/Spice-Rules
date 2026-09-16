<?php

namespace App\Services\FitIsh;

use App\Models\FitIshSession;
use App\Models\FitIshSessionGraphPoint;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class BuildFitIshYou
{
    public function __construct(private readonly BuildFitIshZoneRadar $zones) {}

    /**
     * @return array{
     *     hasData: bool,
     *     stats: array{classes: int, longestStreak: int, totalCalories: int, maxCalories: int},
     *     heartrate: array{floor: int, ceiling: int, columns: list<array{min: int, max: int, average: int|null, bottom: float, height: float}>},
     *     heatmap: array{weeks: list<list<array{date: string, level: int, color: string|null, title: string}>>, months: list<array{label: string, index: int}>},
     *     monthly: array{labels: list<string>, datasets: list<array{label: string, color: string, values: list<int>}>},
     *     typeRadar: array{labels: list<string>, datasets: list<array{label: string, color: string, values: list<float>}>},
     *     zoneStacks: array{labels: list<string>, datasets: list<array{label: string, color: string, values: list<float>}>},
     *     workouts: list<array{name: string, points: float, calories: int, heartrate: int, count: int, pointsStyle: string, caloriesStyle: string, heartrateStyle: string}>
     * }
     */
    public function handle(User $user): array
    {
        $rows = FitIshSession::query()
            ->toBase()
            ->leftJoin('fit_ish_workouts', 'fit_ish_workouts.id', '=', 'fit_ish_sessions.fit_ish_workout_id')
            ->where('fit_ish_sessions.user_id', $user->id)
            ->orderBy('fit_ish_sessions.class_date')
            ->orderBy('fit_ish_sessions.id')
            ->get([
                'fit_ish_sessions.id',
                'fit_ish_sessions.class_date',
                'fit_ish_sessions.points',
                'fit_ish_sessions.average_heartrate',
                'fit_ish_sessions.max_heartrate',
                'fit_ish_sessions.estimated_calories',
                'fit_ish_sessions.fit_ish_workout_id',
                'fit_ish_workouts.name as workout_name',
                'fit_ish_workouts.display_name as workout_display_name',
                'fit_ish_workouts.type as workout_type',
            ]);

        $typeCharts = $this->zones->forTypes($user);

        return [
            'hasData' => $rows->isNotEmpty(),
            'stats' => $this->stats($rows),
            'heartrate' => $this->heartrate($rows),
            'heatmap' => $this->heatmap($rows),
            'monthly' => $this->monthly($rows),
            'typeRadar' => $typeCharts['radar'],
            'zoneStacks' => $typeCharts['stacks'],
            'workouts' => $this->workouts($rows),
        ];
    }

    /**
     * @param  Collection<int, object>  $rows
     * @return array{classes: int, longestStreak: int, totalCalories: int, maxCalories: int}
     */
    private function stats(Collection $rows): array
    {
        $calories = $rows
            ->map(fn (object $row): ?int => $this->int($row->estimated_calories ?? null))
            ->filter(fn (?int $value): bool => $value !== null)
            ->map(fn (?int $value): int => (int) $value);

        return [
            'classes' => $rows->count(),
            'longestStreak' => $this->longestStreak($rows),
            'totalCalories' => (int) $calories->sum(),
            'maxCalories' => (int) ($calories->max() ?: 0),
        ];
    }

    /**
     * @param  Collection<int, object>  $rows
     */
    private function longestStreak(Collection $rows): int
    {
        $dates = $rows
            ->map(fn (object $row): ?string => $this->dateKey($row->class_date ?? null))
            ->filter()
            ->unique()
            ->sort()
            ->values();

        $longest = 0;
        $current = 0;
        $previous = null;

        foreach ($dates as $date) {
            $day = Carbon::createFromFormat('Y-m-d', $date);

            if ($day === null) {
                continue;
            }

            if ($previous instanceof Carbon && $previous->copy()->addDay()->toDateString() === $date) {
                $current++;
            } else {
                $current = 1;
            }

            $longest = max($longest, $current);
            $previous = $day;
        }

        return $longest;
    }

    /**
     * @param  Collection<int, object>  $rows
     * @return array{floor: int, ceiling: int, columns: list<array{min: int, max: int, average: int|null, bottom: float, height: float}>}
     */
    private function heartrate(Collection $rows): array
    {
        $recent = $rows->slice(-45)->values();
        $ranges = $this->sessionRanges($recent->pluck('id')->all());
        $columns = [];
        $mins = [];
        $maxes = [];

        foreach ($recent as $row) {
            $id = (int) $row->id;
            $average = $this->int($row->average_heartrate ?? null);
            $graphMin = $this->int($ranges[$id]['min'] ?? null);
            $graphMax = $this->int($ranges[$id]['max'] ?? null);
            $min = $graphMin ?? $average ?? $this->int($row->max_heartrate ?? null);
            $max = $graphMax ?? $this->int($row->max_heartrate ?? null) ?? $average;

            if ($min === null || $max === null) {
                continue;
            }

            $max = max($min, $max);
            $mins[] = $min;
            $maxes[] = $max;
            $columns[] = [
                'min' => $min,
                'max' => $max,
                'average' => $average,
            ];
        }

        $floor = max(40, ($mins === [] ? 60 : min($mins)) - 8);
        $ceiling = max($floor + 40, $maxes === [] ? $floor + 80 : max($maxes) + 4);
        $span = max($ceiling - $floor, 1);

        return [
            'floor' => $floor,
            'ceiling' => $ceiling,
            'columns' => array_map(function (array $column) use ($floor, $span): array {
                $bottom = $this->pct($column['min'], $floor, $span);
                $top = $this->pct($column['max'], $floor, $span);

                return [
                    'min' => $column['min'],
                    'max' => $column['max'],
                    'average' => $column['average'],
                    'bottom' => $bottom,
                    'height' => max(1.5, $top - $bottom),
                ];
            }, $columns),
        ];
    }

    /**
     * @param  list<int>  $sessionIds
     * @return array<int, array{min: int, max: int}>
     */
    private function sessionRanges(array $sessionIds): array
    {
        if ($sessionIds === []) {
            return [];
        }

        return FitIshSessionGraphPoint::query()
            ->toBase()
            ->whereIn('fit_ish_session_id', $sessionIds)
            ->whereNotNull('bpm_min')
            ->groupBy('fit_ish_session_id')
            ->selectRaw('fit_ish_session_id, MIN(bpm_min) as bpm_min, MAX(COALESCE(bpm_max, bpm_min)) as bpm_max')
            ->get()
            ->mapWithKeys(fn (object $row): array => [
                (int) $row->fit_ish_session_id => [
                    'min' => (int) $row->bpm_min,
                    'max' => (int) $row->bpm_max,
                ],
            ])
            ->all();
    }

    /**
     * @param  Collection<int, object>  $rows
     * @return array{weeks: list<list<array{date: string, level: int, color: string|null, title: string}>>, months: list<array{label: string, index: int}>}
     */
    private function heatmap(Collection $rows): array
    {
        $byDate = $rows
            ->groupBy(fn (object $row): string => $this->dateKey($row->class_date ?? null) ?? '')
            ->filter(fn (Collection $group, string $date): bool => $date !== '');
        $points = $byDate
            ->map(fn (Collection $group): float => (float) $group->max('points'))
            ->filter(fn (float $value): bool => $value > 0);
        $minPoints = (float) ($points->min() ?: 0);
        $maxPoints = (float) ($points->max() ?: 0);

        $weekStart = today()->copy()->startOfWeek(Carbon::SUNDAY)->startOfDay();
        $origin = $weekStart->copy()->subWeeks(52);
        $weeks = [];
        $months = [];
        $lastMonth = null;

        for ($week = 0; $week < 53; $week++) {
            $sunday = $origin->copy()->addWeeks($week);
            $cells = [];

            for ($day = 0; $day < 7; $day++) {
                $date = $sunday->copy()->addDays($day);
                $key = $date->toDateString();
                $group = $byDate->get($key);
                $count = $group instanceof Collection ? $group->count() : 0;
                $best = $group instanceof Collection ? (float) $group->max('points') : 0.0;
                $level = 0;

                if ($count > 0) {
                    $level = $maxPoints <= $minPoints
                        ? 3
                        : 1 + (int) floor(3 * ($best - $minPoints) / ($maxPoints - $minPoints));
                    $level = max(1, min(4, $level));
                }

                $cells[] = [
                    'date' => $key,
                    'level' => $level,
                    'color' => match ($level) {
                        1 => '#F4C4C1',
                        2 => '#E56D67',
                        3 => '#D82820',
                        4 => '#8A1612',
                        default => null,
                    },
                    'title' => $count === 0
                        ? $date->format('j M')
                        : $date->format('j M').' · '.$count.' '.Str::plural('class', $count).($best > 0 ? ' · '.number_format($best, 1).' pts' : ''),
                ];
            }

            $month = $sunday->format('M');

            if ($week === 0 || $sunday->day <= 7 && $month !== $lastMonth) {
                $months[] = [
                    'label' => $month,
                    'index' => $week,
                ];
                $lastMonth = $month;
            }

            $weeks[] = $cells;
        }

        return [
            'weeks' => $weeks,
            'months' => $months,
        ];
    }

    /**
     * @param  Collection<int, object>  $rows
     * @return array{labels: list<string>, datasets: list<array{label: string, color: string, values: list<int>}>}
     */
    private function monthly(Collection $rows): array
    {
        $cursor = today()->copy()->startOfMonth()->subMonths(11);
        $labels = [];
        $keys = [];

        for ($i = 0; $i < 12; $i++) {
            $month = $cursor->copy()->addMonths($i);
            $labels[] = $month->format('M');
            $keys[] = $month->format('Y-m');
        }

        $counts = [];

        foreach (['resistance', 'hybrid', 'cardio', 'other'] as $type) {
            $counts[$type] = array_fill(0, 12, 0);
        }

        foreach ($rows as $row) {
            $date = $this->dateKey($row->class_date ?? null);

            if ($date === null) {
                continue;
            }

            $key = substr($date, 0, 7);
            $index = array_search($key, $keys, true);

            if ($index === false) {
                continue;
            }

            $type = $this->zones->typeKey($row->workout_type ?? null);
            $counts[$type][$index]++;
        }

        $datasets = [];

        foreach (['resistance', 'hybrid', 'cardio', 'other'] as $type) {
            if (array_sum($counts[$type]) === 0) {
                continue;
            }

            $datasets[] = [
                'label' => $this->zones->typeLabel($type),
                'color' => BuildFitIshZoneRadar::TYPE_COLORS[$type],
                'values' => $counts[$type],
            ];
        }

        return [
            'labels' => $labels,
            'datasets' => $datasets,
        ];
    }

    /**
     * @param  Collection<int, object>  $rows
     * @return list<array{name: string, points: float, calories: int, heartrate: int, count: int, pointsStyle: string, caloriesStyle: string, heartrateStyle: string}>
     */
    private function workouts(Collection $rows): array
    {
        $grouped = $rows
            ->filter(fn (object $row): bool => $row->fit_ish_workout_id !== null)
            ->groupBy(fn (object $row): int => (int) $row->fit_ish_workout_id);

        $workouts = $grouped
            ->map(function (Collection $group): array {
                $first = $group->first();
                $name = trim((string) ($first->workout_display_name ?? $first->workout_name ?? 'Workout'));

                return [
                    'name' => $name !== '' ? $name : 'Workout',
                    'points' => round((float) $group->avg('points'), 1),
                    'calories' => (int) round((float) $group->avg('estimated_calories')),
                    'heartrate' => (int) round((float) $group->avg('average_heartrate')),
                    'count' => $group->count(),
                ];
            })
            ->sortBy([
                ['points', 'desc'],
                ['count', 'desc'],
                ['name', 'asc'],
            ])
            ->values();

        $pointMin = (float) ($workouts->min('points') ?: 0);
        $pointMax = (float) ($workouts->max('points') ?: 0);
        $calMin = (float) ($workouts->min('calories') ?: 0);
        $calMax = (float) ($workouts->max('calories') ?: 0);
        $hrMin = (float) ($workouts->min('heartrate') ?: 0);
        $hrMax = (float) ($workouts->max('heartrate') ?: 0);

        return $workouts
            ->map(fn (array $workout): array => [
                ...$workout,
                'pointsStyle' => $this->heatStyle($workout['points'], $pointMin, $pointMax),
                'caloriesStyle' => $this->heatStyle($workout['calories'], $calMin, $calMax),
                'heartrateStyle' => $this->heatStyle($workout['heartrate'], $hrMin, $hrMax),
            ])
            ->all();
    }

    private function heatStyle(float $value, float $min, float $max): string
    {
        $t = $max <= $min ? 0.6 : max(0, min(1, ($value - $min) / ($max - $min)));
        $background = $t < 0.5
            ? $this->mix('#93C5FD', '#7C3AED', $t / 0.5)
            : $this->mix('#7C3AED', '#B91C1C', ($t - 0.5) / 0.5);
        $text = $this->luma($background) > 0.55 ? '#1C1C1C' : '#FFFDF6';

        return 'background: '.$background.'; color: '.$text;
    }

    private function mix(string $from, string $to, float $t): string
    {
        $t = max(0, min(1, $t));
        $start = $this->rgb($from);
        $end = $this->rgb($to);

        return sprintf(
            '#%02X%02X%02X',
            (int) round($start[0] + ($end[0] - $start[0]) * $t),
            (int) round($start[1] + ($end[1] - $start[1]) * $t),
            (int) round($start[2] + ($end[2] - $start[2]) * $t),
        );
    }

    /**
     * @return array{0: int, 1: int, 2: int}
     */
    private function rgb(string $hex): array
    {
        $hex = ltrim($hex, '#');

        return [
            hexdec(substr($hex, 0, 2)),
            hexdec(substr($hex, 2, 2)),
            hexdec(substr($hex, 4, 2)),
        ];
    }

    private function luma(string $hex): float
    {
        [$red, $green, $blue] = $this->rgb($hex);

        return (0.299 * $red + 0.587 * $green + 0.114 * $blue) / 255;
    }

    private function pct(int $value, int $floor, int $span): float
    {
        return round(100 * max(0, min($span, $value - $floor)) / $span, 2);
    }

    private function int(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }

    private function dateKey(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof Carbon) {
            return $value->toDateString();
        }

        return substr((string) $value, 0, 10) ?: null;
    }
}
