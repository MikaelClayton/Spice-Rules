<?php

namespace App\Services\Spirdle;

use App\Models\SpirdlePlay;
use App\Models\SpirdlePuzzle;
use App\Models\User;

class BuildSpirdleChallenges
{
    public function __construct(private readonly RankSpirdlePlays $ranker) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function handle(?User $viewer = null): array
    {
        if ($viewer === null) {
            return [];
        }

        return SpirdlePuzzle::query()
            ->with([
                'word',
                'plays' => fn ($query) => $query->finished()->with('user'),
            ])
            ->whereHas(
                'plays',
                fn ($query) => $query->finished()->where('user_id', $viewer->id),
            )
            ->orderByDesc('play_date')
            ->get()
            ->map(fn (SpirdlePuzzle $puzzle): array => $this->daily($puzzle))
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function daily(SpirdlePuzzle $puzzle): array
    {
        $date = $puzzle->play_date?->toDateString() ?? '';
        $plays = $puzzle->plays;
        $sorted = $this->ranker->sorted($plays);
        $ranks = $this->ranker->ranks($plays);
        $word = $puzzle->solution();

        return [
            'token' => $date,
            'date' => $date,
            'label' => $puzzle->play_date?->toFormattedDateString() ?? $date,
            'playerCount' => $plays->count(),
            'locked' => false,
            'word' => $word,
            'standings' => $sorted
                ->filter(fn (SpirdlePlay $play): bool => $play->user !== null)
                ->map(fn (SpirdlePlay $play): array => [
                    'playerId' => $play->user_id,
                    'label' => $play->user?->name ?? 'Unknown',
                    'color' => $play->user?->boardColor() ?? '#283030',
                    'place' => $ranks[(int) $play->id] ?? $sorted->count(),
                    'attempts' => $play->guess_count,
                    'missed' => $play->invalid_word_count,
                    'time' => $play->durationLabel(),
                    'won' => $play->won,
                    'word' => $play->won ? $word : null,
                ])
                ->values()
                ->all(),
            'board' => [
                'results' => $sorted,
                'ranks' => $ranks,
            ],
        ];
    }
}
