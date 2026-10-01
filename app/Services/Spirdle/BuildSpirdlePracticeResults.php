<?php

namespace App\Services\Spirdle;

use App\Models\SpirdlePractice;
use App\Models\User;
use Illuminate\Support\Collection;

class BuildSpirdlePracticeResults
{
    /**
     * @return array{
     *     results: Collection<int, SpirdlePractice>,
     *     ranks: array<int, int>
     * }
     */
    public function handle(User $user): array
    {
        $results = SpirdlePractice::query()
            ->with('word')
            ->whereBelongsTo($user)
            ->finished()
            ->orderByDesc('finished_at')
            ->orderByDesc('id')
            ->get();

        $ranks = [];
        foreach ($results as $index => $practice) {
            $ranks[(int) $practice->id] = $index + 1;
        }

        return [
            'results' => $results,
            'ranks' => $ranks,
        ];
    }
}
