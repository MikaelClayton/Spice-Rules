<?php

namespace App\Services\Spirdle;

use App\Models\SpirdlePlay;
use App\Models\SpirdlePuzzle;
use App\Models\User;
use Illuminate\Support\Collection;

class BuildSpirdleToday
{
    public function __construct(
        private readonly EnsureTodaysSpirdlePuzzle $ensureTodaysSpirdlePuzzle,
        private readonly RankSpirdlePlays $ranker,
    ) {}

    /**
     * @return array{
     *     puzzle: SpirdlePuzzle|null,
     *     viewerPlay: SpirdlePlay|null,
     *     hasPlayed: bool,
     *     results: Collection<int, SpirdlePlay>,
     *     ranks: array<int, int>,
     *     fewestGuesses: int|null,
     *     fastestMs: int|null,
     *     fewestInvalid: int|null,
     *     revision: string
     * }
     */
    public function handle(?User $viewer = null): array
    {
        $puzzle = $this->ensureTodaysSpirdlePuzzle->handle();
        $plays = $puzzle === null
            ? collect()
            : SpirdlePlay::query()
                ->with('user')
                ->whereBelongsTo($puzzle, 'puzzle')
                ->finished()
                ->get();

        $sorted = $this->ranker->sorted($plays);
        $winners = $sorted->filter(fn (SpirdlePlay $play): bool => $play->won);
        $viewerPlay = $puzzle === null || $viewer === null
            ? null
            : SpirdlePlay::query()
                ->whereBelongsTo($viewer)
                ->whereBelongsTo($puzzle, 'puzzle')
                ->first();

        return [
            'puzzle' => $puzzle,
            'viewerPlay' => $viewerPlay,
            'hasPlayed' => $viewerPlay?->isFinished() === true,
            'results' => $sorted,
            'ranks' => $this->ranker->ranks($plays),
            'fewestGuesses' => $winners->count() >= 2 ? $winners->min('guess_count') : null,
            'fastestMs' => $winners->count() >= 2 ? $winners->min('duration_ms') : null,
            'fewestInvalid' => $winners->count() >= 2 ? $winners->min('invalid_word_count') : null,
            'revision' => $this->revision($puzzle),
        ];
    }

    public function revision(?SpirdlePuzzle $puzzle = null): string
    {
        $puzzle ??= $this->ensureTodaysSpirdlePuzzle->handle();

        if ($puzzle === null) {
            return '0:none';
        }

        $row = SpirdlePlay::query()
            ->whereBelongsTo($puzzle, 'puzzle')
            ->finished()
            ->toBase()
            ->selectRaw('count(*) as aggregate_count, max(updated_at) as latest_updated_at')
            ->first();

        return $puzzle->id.':'.(int) ($row->aggregate_count ?? 0).':'.(string) ($row->latest_updated_at ?? 'none');
    }
}
