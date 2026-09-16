<?php

namespace App\Services\FitIsh;

use App\Models\FitIshSession;
use App\Models\FitIshStudio;
use App\Models\FitIshWorkout;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class PersistFitIshSession
{
    public function __construct(private readonly StoreFitIshWorkoutLogo $logos) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handle(User $user, array $payload): ?FitIshSession
    {
        $data = is_array($payload['data'] ?? null) ? $payload['data'] : $payload;
        $sessionId = $data['sessionId'] ?? null;

        if (! is_string($sessionId) || $sessionId === '') {
            return null;
        }

        $workoutPayload = is_array($data['workout'] ?? null) ? $data['workout'] : [];
        $logoUrl = $this->string(data_get($workoutPayload, 'logo.url'));

        $session = DB::transaction(function () use ($user, $data, $sessionId, $workoutPayload): FitIshSession {
            $studio = $this->studio($user, is_array($data['studio'] ?? null) ? $data['studio'] : []);
            $workout = $this->workout($workoutPayload);
            $classInfo = is_array($data['classInfo'] ?? null) ? $data['classInfo'] : [];
            $summary = is_array($data['summary'] ?? null) ? $data['summary'] : [];
            $heartrate = is_array($data['heartrate'] ?? null) ? $data['heartrate'] : [];
            $inputs = is_array($heartrate['inputs'] ?? null) ? $heartrate['inputs'] : [];
            $graph = is_array($data['graph'] ?? null) ? $data['graph'] : [];
            $method = is_array($heartrate['calculationMethod'] ?? null) ? $heartrate['calculationMethod'] : [];

            $session = FitIshSession::query()->updateOrCreate(
                ['session_id' => $sessionId],
                [
                    'user_id' => $user->id,
                    'fit_ish_studio_id' => $studio?->id,
                    'fit_ish_workout_id' => $workout?->id,
                    'class_date' => $this->classDate($classInfo, $sessionId),
                    'class_time' => $this->classTime($classInfo, $sessionId),
                    'started_at' => $this->startedAt($classInfo),
                    'timezone' => $this->string($classInfo['timezone'] ?? $studio?->timezone),
                    'localized_date_time' => $this->string($classInfo['localizedDateTime'] ?? null),
                    'duration_in_minutes' => $this->int($classInfo['durationInMinutes'] ?? null),
                    'tracked_duration_seconds' => $this->int($summary['trackedDurationInSeconds'] ?? null),
                    'points' => $this->float($summary['points'] ?? null),
                    'average_heartrate' => $this->int(data_get($summary, 'heartrate.average')),
                    'max_heartrate' => $this->int(data_get($summary, 'heartrate.max')),
                    'estimated_calories' => $this->int($summary['estimatedCalories'] ?? null),
                    'heartrate_method' => $this->string($method['name'] ?? null),
                    'max_hr_default' => $this->int(data_get($inputs, 'maxHR.default')),
                    'max_hr_override' => $this->int(data_get($inputs, 'maxHR.override')),
                    'max_hr_value' => $this->int(data_get($inputs, 'maxHR.value')),
                    'resting_hr_default' => $this->int(data_get($inputs, 'restingHR.default')),
                    'resting_hr_override' => $this->int(data_get($inputs, 'restingHR.override')),
                    'resting_hr_value' => $this->int(data_get($inputs, 'restingHR.value')),
                    'graph_type' => $this->string($graph['type'] ?? null),
                ],
            );

            $this->syncZones($session, is_array($heartrate['zones'] ?? null) ? $heartrate['zones'] : []);
            $this->syncGraph($session, is_array($graph['timeSeries'] ?? null) ? $graph['timeSeries'] : []);
            $this->rememberSerial($user, $sessionId);

            return $session;
        });

        if ($session->workout === null && $session->fit_ish_workout_id !== null) {
            $session->load('workout');
        }

        if ($session->workout !== null && $logoUrl !== null) {
            $this->logos->handle($session->workout, $logoUrl);
        }

        return $session->load(['studio', 'workout', 'zones', 'graphPoints', 'user']);
    }

    /**
     * @param  array<string, mixed>  $studio
     */
    private function studio(User $user, array $studio): ?FitIshStudio
    {
        $code = $this->string($studio['code'] ?? null);
        $externalId = $this->int($studio['studioId'] ?? null);

        if ($code === null && $externalId === null) {
            return null;
        }

        $record = null;

        if ($externalId !== null) {
            $record = FitIshStudio::query()->where('external_id', $externalId)->first();
        }

        if ($record === null && $code !== null) {
            $record = FitIshStudio::query()->where('code', $code)->first();
        }

        $attributes = [
            'external_id' => $externalId ?? $record?->external_id ?? (crc32($code ?? 'studio') & 0x7FFFFFFF),
            'name' => $this->string($studio['name'] ?? null) ?? $record?->name ?? $code ?? 'Studio',
            'code' => $code ?? $record?->code ?? 'unknown',
            'timezone' => $this->string($studio['timezone'] ?? null) ?? $record?->timezone ?? 'UTC',
            'is_loaner' => (bool) ($studio['isLoaner'] ?? $record?->is_loaner ?? false),
        ];

        if ($record === null) {
            $record = FitIshStudio::query()->create($attributes);
        } else {
            $record->fill($attributes)->save();
        }

        $user->fitIshStudios()->syncWithoutDetaching([$record->id]);

        return $record;
    }

    /**
     * @param  array<string, mixed>  $workout
     */
    private function workout(array $workout): ?FitIshWorkout
    {
        $name = $this->string($workout['name'] ?? null);

        if ($name === null) {
            return null;
        }

        $type = is_array($workout['type'] ?? null)
            ? $this->string($workout['type']['name'] ?? null)
            : $this->string($workout['type'] ?? null);

        return FitIshWorkout::query()->updateOrCreate(
            ['name' => $name],
            [
                'display_name' => $this->string($workout['displayName'] ?? null) ?? $name,
                'type' => $type ?? 'other',
                'description' => $this->string($workout['description'] ?? null),
                'logo_url' => $this->string(data_get($workout, 'logo.url')),
            ],
        );
    }

    /**
     * @param  list<array<string, mixed>>  $zones
     */
    private function syncZones(FitIshSession $session, array $zones): void
    {
        $session->zones()->delete();

        foreach ($zones as $zone) {
            if (! is_array($zone)) {
                continue;
            }

            $session->zones()->create([
                'zone_number' => $this->int($zone['zoneId'] ?? null) ?? 0,
                'name' => $this->string($zone['name'] ?? null) ?? 'Zone',
                'description' => $this->string($zone['description'] ?? null),
                'color_hex' => $this->string($zone['colorHex'] ?? null),
                'min_percentage' => $this->float($zone['minPercentage'] ?? null),
                'max_percentage' => $this->float($zone['maxPercentage'] ?? null),
                'min_bpm' => $this->int($zone['minBpm'] ?? null),
                'max_bpm' => $this->int($zone['maxBpm'] ?? null),
                'bpm_label' => $this->string($zone['bpmLabel'] ?? null),
                'duration_seconds' => $this->int(data_get($zone, 'computedDuration.seconds')),
                'duration_label' => $this->string(data_get($zone, 'computedDuration.label')),
                'percentage_value' => $this->float(data_get($zone, 'computedPercentage.value')),
                'percentage_label' => $this->string(data_get($zone, 'computedPercentage.label')),
            ]);
        }
    }

    /**
     * @param  list<array<string, mixed>>  $points
     */
    private function syncGraph(FitIshSession $session, array $points): void
    {
        $session->graphPoints()->delete();

        foreach ($points as $point) {
            if (! is_array($point)) {
                continue;
            }

            $session->graphPoints()->create([
                'minute' => $this->int($point['minute'] ?? null) ?? 0,
                'type' => $this->string($point['type'] ?? null) ?? 'noData',
                'bpm_min' => $this->int(data_get($point, 'bpm.min')),
                'bpm_max' => $this->int(data_get($point, 'bpm.max')),
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $classInfo
     */
    private function classDate(array $classInfo, string $sessionId): string
    {
        $date = $this->string($classInfo['date'] ?? null);

        if ($date !== null) {
            return $date;
        }

        return substr($sessionId, 0, 10);
    }

    /**
     * @param  array<string, mixed>  $classInfo
     */
    private function classTime(array $classInfo, string $sessionId): string
    {
        $time = $this->string($classInfo['time'] ?? null);

        if ($time !== null) {
            return $time;
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}_(\d{2})(\d{2})/', $sessionId, $matches) === 1) {
            return $matches[1].':'.$matches[2].':00';
        }

        return '00:00:00';
    }

    /**
     * @param  array<string, mixed>  $classInfo
     */
    private function startedAt(array $classInfo): ?Carbon
    {
        $timestamp = $this->int($classInfo['timestamp'] ?? null);

        if ($timestamp === null) {
            return null;
        }

        return Carbon::createFromTimestamp($timestamp);
    }

    private function rememberSerial(User $user, string $sessionId): void
    {
        if (filled($user->fit_ish_serial)) {
            return;
        }

        if (preg_match('/:serial:(.+)$/', $sessionId, $matches) !== 1) {
            return;
        }

        $user->forceFill(['fit_ish_serial' => $matches[1]])->save();
    }

    private function string(mixed $value): ?string
    {
        if (! is_string($value) && ! is_numeric($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function int(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }

    private function float(mixed $value): ?float
    {
        return is_numeric($value) ? (float) $value : null;
    }
}
