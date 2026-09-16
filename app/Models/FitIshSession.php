<?php

namespace App\Models;

use Database\Factories\FitIshSessionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'session_id',
    'user_id',
    'fit_ish_studio_id',
    'fit_ish_workout_id',
    'class_date',
    'class_time',
    'started_at',
    'timezone',
    'localized_date_time',
    'duration_in_minutes',
    'tracked_duration_seconds',
    'points',
    'average_heartrate',
    'max_heartrate',
    'estimated_calories',
    'heartrate_method',
    'max_hr_default',
    'max_hr_override',
    'max_hr_value',
    'resting_hr_default',
    'resting_hr_override',
    'resting_hr_value',
    'graph_type',
])]
class FitIshSession extends Model
{
    /** @use HasFactory<FitIshSessionFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'class_date' => 'date',
            'started_at' => 'datetime',
            'duration_in_minutes' => 'integer',
            'tracked_duration_seconds' => 'integer',
            'points' => 'decimal:2',
            'average_heartrate' => 'integer',
            'max_heartrate' => 'integer',
            'estimated_calories' => 'integer',
            'max_hr_default' => 'integer',
            'max_hr_override' => 'integer',
            'max_hr_value' => 'integer',
            'resting_hr_default' => 'integer',
            'resting_hr_override' => 'integer',
            'resting_hr_value' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<FitIshStudio, $this>
     */
    public function studio(): BelongsTo
    {
        return $this->belongsTo(FitIshStudio::class, 'fit_ish_studio_id');
    }

    /**
     * @return BelongsTo<FitIshWorkout, $this>
     */
    public function workout(): BelongsTo
    {
        return $this->belongsTo(FitIshWorkout::class, 'fit_ish_workout_id');
    }

    /**
     * @return HasMany<FitIshSessionZone, $this>
     */
    public function zones(): HasMany
    {
        return $this->hasMany(FitIshSessionZone::class)->orderBy('zone_number')->orderBy('id');
    }

    /**
     * @return HasMany<FitIshSessionGraphPoint, $this>
     */
    public function graphPoints(): HasMany
    {
        return $this->hasMany(FitIshSessionGraphPoint::class)->orderBy('minute')->orderBy('id');
    }

    public function pointsValue(): float
    {
        return (float) $this->points;
    }

    public function hardZoneSeconds(): int
    {
        return (int) $this->zones
            ->filter(fn (FitIshSessionZone $zone): bool => $zone->isHardEffort())
            ->sum('duration_seconds');
    }

    public function zoneForBpm(?int $bpm): ?FitIshSessionZone
    {
        if ($bpm === null) {
            return null;
        }

        $zones = $this->zones->sortBy('zone_number')->values();

        foreach ($zones as $index => $zone) {
            $min = (int) $zone->min_bpm;
            $isLast = $index === $zones->count() - 1;

            if ($isLast) {
                if ($bpm >= $min) {
                    return $zone;
                }

                continue;
            }

            $nextMin = (int) $zones[$index + 1]->min_bpm;

            if ($bpm >= $min && $bpm < $nextMin) {
                return $zone;
            }
        }

        return null;
    }

    /**
     * @return array{
     *     floor: int,
     *     ceiling: int,
     *     average: int|null,
     *     averagePct: float|null,
     *     endMinute: int,
     *     bands: list<array{color: string, fill: string, bottom: float, height: float, name: string}>,
     *     columns: list<array{minute: int, recorded: bool, bottom: float, height: float, color: string, label: string}>
     * }|null
     */
    public function heartrateChart(): ?array
    {
        if ($this->graphPoints->isEmpty()) {
            return null;
        }

        $recorded = $this->graphPoints->filter(fn (FitIshSessionGraphPoint $point): bool => $point->hasRecording());
        $floorCandidates = array_values(array_filter([
            $this->resting_hr_value,
            $recorded->min('bpm_min'),
        ], fn (mixed $value): bool => $value !== null));
        $floor = max(40, ($floorCandidates === [] ? 50 : min($floorCandidates)) - 10);
        $ceilingCandidates = array_values(array_filter([
            $this->max_heartrate,
            $this->max_hr_value,
            $recorded->max('bpm_max'),
        ], fn (mixed $value): bool => $value !== null));
        $ceiling = max($floor + 40, $ceilingCandidates === [] ? $floor + 80 : max($ceilingCandidates));
        $span = max($ceiling - $floor, 1);
        $average = $this->average_heartrate;
        $endMinute = max(
            1,
            (int) ($this->graphPoints->max('minute') ?: 0),
            (int) ($this->duration_in_minutes ?: 0),
        );
        $zones = $this->zones->sortBy('zone_number')->values();

        return [
            'floor' => $floor,
            'ceiling' => $ceiling,
            'average' => $average,
            'averagePct' => $average === null ? null : $this->chartPct($average, $floor, $span),
            'endMinute' => $endMinute,
            'bands' => $zones
                ->map(function (FitIshSessionZone $zone, int $index) use ($floor, $ceiling, $span, $zones): array {
                    $isLast = $index === $zones->count() - 1;
                    $low = max($floor, (int) $zone->min_bpm);
                    $high = $isLast ? $ceiling : min($ceiling, max((int) $zone->max_bpm, $low + 1));

                    return [
                        'color' => $zone->swatch(),
                        'fill' => $zone->swatch().'33',
                        'bottom' => $this->chartPct($low, $floor, $span),
                        'height' => max(0.0, $this->chartPct($high, $floor, $span) - $this->chartPct($low, $floor, $span)),
                        'name' => $zone->shortName(),
                    ];
                })
                ->filter(fn (array $band): bool => $band['height'] > 0)
                ->values()
                ->all(),
            'columns' => $this->graphPoints
                ->map(function (FitIshSessionGraphPoint $point) use ($floor, $span): array {
                    if (! $point->hasRecording()) {
                        return [
                            'minute' => (int) $point->minute,
                            'recorded' => false,
                            'bottom' => 0.0,
                            'height' => 4.0,
                            'color' => '#9CA3AF',
                            'label' => 'Minute '.$point->minute.': no reading',
                        ];
                    }

                    $min = (int) ($point->bpm_min ?? $point->bpm_max ?? $floor);
                    $max = (int) ($point->bpm_max ?? $min);
                    $zone = $this->zoneForBpm($max);
                    $bottom = $this->chartPct($min, $floor, $span);
                    $top = $this->chartPct($max, $floor, $span);

                    return [
                        'minute' => (int) $point->minute,
                        'recorded' => true,
                        'bottom' => $bottom,
                        'height' => max(1.5, $top - $bottom),
                        'color' => $zone?->swatch() ?? '#888888',
                        'label' => 'Minute '.$point->minute.': '.$min.'–'.$max.' bpm'.($zone ? ' · '.$zone->shortName() : ''),
                    ];
                })
                ->all(),
        ];
    }

    private function chartPct(int $bpm, int $floor, int $span): float
    {
        return round(100 * max(0, min($span, $bpm - $floor)) / $span, 2);
    }
}
