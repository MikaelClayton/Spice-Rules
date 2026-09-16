<?php

namespace App\Services\FitIsh;

use App\Models\FitIshSession;
use App\Models\FitIshSessionZone;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class BuildFitIshZoneRadar
{
    /**
     * @var array<int, string>
     */
    public const ZONE_COLORS = [
        1 => '#326EC8',
        2 => '#2BBFBD',
        3 => '#5CB85C',
        4 => '#F2911B',
        5 => '#E23B3B',
    ];

    /**
     * @var array<string, string>
     */
    public const TYPE_COLORS = [
        'resistance' => '#1E3A8A',
        'hybrid' => '#BE185D',
        'cardio' => '#C2410C',
        'other' => '#78716C',
    ];

    /**
     * @param  Collection<int, FitIshSession>  $sessions
     * @return array{labels: list<string>, datasets: list<array{label: string, color: string, values: list<float>}>}
     */
    public function forPeople(Collection $sessions): array
    {
        $picked = [];

        foreach ($sessions as $session) {
            $userId = (int) $session->user_id;

            if (! isset($picked[$userId])) {
                $picked[$userId] = $session;
            }
        }

        $datasets = [];

        foreach ($picked as $session) {
            $values = $this->zonePercents($session->zones);

            if ($this->isEmpty($values)) {
                continue;
            }

            $label = trim(Str::of((string) $session->user?->name)->before(' ')->toString());

            $datasets[] = [
                'label' => $label !== '' ? $label : 'Unknown',
                'color' => $session->user instanceof User ? $session->user->boardColor() : '#283030',
                'values' => $values,
            ];
        }

        return [
            'labels' => $this->zoneLabels(),
            'datasets' => $datasets,
        ];
    }

    /**
     * @return array{
     *     radar: array{labels: list<string>, datasets: list<array{label: string, color: string, values: list<float>}>},
     *     stacks: array{labels: list<string>, datasets: list<array{label: string, color: string, values: list<float>}>}
     * }
     */
    public function forTypes(User $user): array
    {
        $rows = FitIshSessionZone::query()
            ->toBase()
            ->join('fit_ish_sessions', 'fit_ish_sessions.id', '=', 'fit_ish_session_zones.fit_ish_session_id')
            ->leftJoin('fit_ish_workouts', 'fit_ish_workouts.id', '=', 'fit_ish_sessions.fit_ish_workout_id')
            ->where('fit_ish_sessions.user_id', $user->id)
            ->groupByRaw('fit_ish_workouts.type, fit_ish_session_zones.zone_number')
            ->orderBy('fit_ish_session_zones.zone_number')
            ->selectRaw('fit_ish_workouts.type as workout_type')
            ->selectRaw('fit_ish_session_zones.zone_number as zone_number')
            ->selectRaw('AVG(fit_ish_session_zones.percentage_value) as avg_pct')
            ->selectRaw('MAX(fit_ish_session_zones.color_hex) as color_hex')
            ->get();

        $byType = [];

        foreach ($rows as $row) {
            $type = $this->typeKey($row->workout_type ?? null);
            $zone = max(1, min(5, (int) $row->zone_number));
            $byType[$type] ??= array_fill(1, 5, 0.0);
            $byType[$type][$zone] = round((float) $row->avg_pct, 1);
        }

        $radarDatasets = [];
        $stackLabels = [];
        $stackValues = [
            1 => [],
            2 => [],
            3 => [],
            4 => [],
            5 => [],
        ];

        foreach (['resistance', 'hybrid', 'cardio', 'other'] as $type) {
            if (! isset($byType[$type]) || $this->isEmpty(array_values($byType[$type]))) {
                continue;
            }

            $values = [];

            for ($zone = 1; $zone <= 5; $zone++) {
                $values[] = $byType[$type][$zone];
                $stackValues[$zone][] = $byType[$type][$zone];
            }

            $radarDatasets[] = [
                'label' => $this->typeLabel($type),
                'color' => self::TYPE_COLORS[$type],
                'values' => $values,
            ];
            $stackLabels[] = $this->typeLabel($type);
        }

        $stackDatasets = [];

        for ($zone = 1; $zone <= 5; $zone++) {
            if ($stackLabels === []) {
                break;
            }

            $stackDatasets[] = [
                'label' => 'Zone '.$zone,
                'color' => self::ZONE_COLORS[$zone],
                'values' => $stackValues[$zone],
            ];
        }

        return [
            'radar' => [
                'labels' => $this->zoneLabels(),
                'datasets' => $radarDatasets,
            ],
            'stacks' => [
                'labels' => $stackLabels,
                'datasets' => $stackDatasets,
            ],
        ];
    }

    /**
     * @return list<string>
     */
    public function zoneLabels(): array
    {
        return ['Zone 1', 'Zone 2', 'Zone 3', 'Zone 4', 'Zone 5'];
    }

    public function typeKey(?string $type): string
    {
        $key = Str::lower(trim((string) $type));

        return in_array($key, ['resistance', 'hybrid', 'cardio'], true) ? $key : 'other';
    }

    public function typeLabel(string $type): string
    {
        return match ($type) {
            'resistance' => 'Resistance',
            'hybrid' => 'Hybrid',
            'cardio' => 'Cardio',
            default => 'Other',
        };
    }

    /**
     * @param  Collection<int, FitIshSessionZone>|iterable<int, FitIshSessionZone>  $zones
     * @return list<float>
     */
    private function zonePercents(iterable $zones): array
    {
        $values = array_fill(1, 5, 0.0);

        foreach ($zones as $zone) {
            $number = max(1, min(5, (int) $zone->zone_number));
            $values[$number] = round((float) $zone->percentage_value, 1);
        }

        return array_values($values);
    }

    /**
     * @param  list<float>  $values
     */
    private function isEmpty(array $values): bool
    {
        foreach ($values as $value) {
            if ($value > 0) {
                return false;
            }
        }

        return true;
    }
}
