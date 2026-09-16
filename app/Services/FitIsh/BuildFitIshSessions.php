<?php

namespace App\Services\FitIsh;

use App\Models\FitIshSession;
use Illuminate\Support\Carbon;

class BuildFitIshSessions
{
    /**
     * @return list<array{date: string, label: string}>
     */
    public function dates(): array
    {
        return FitIshSession::query()
            ->select('class_date')
            ->distinct()
            ->orderByDesc('class_date')
            ->pluck('class_date')
            ->map(function (mixed $date): array {
                $day = Carbon::parse((string) $date);

                return [
                    'date' => $day->toDateString(),
                    'label' => $day->toFormattedDateString(),
                ];
            })
            ->values()
            ->all();
    }
}
