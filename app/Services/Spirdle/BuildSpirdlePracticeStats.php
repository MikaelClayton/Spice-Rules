<?php

namespace App\Services\Spirdle;

use App\Models\SpirdlePractice;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class BuildSpirdlePracticeStats
{
    public const MIN_PEERS_FOR_PACE = 2;

    public const STRUGGLE_LIMIT = 8;

    /**
     * @return array{
     *     hasData: bool,
     *     inProgress: bool,
     *     stats: array{
     *         played: int,
     *         wins: int,
     *         winPercent: int,
     *         averageGuesses: float|null,
     *         averageDurationMs: int|null,
     *         averageDurationLabel: string|null,
     *         missedMoreThanOnce: int
     *     },
     *     pace: array{
     *         available: bool,
     *         fasterThanPercent: int|null,
     *         band: string|null,
     *         summary: string|null,
     *         sampleSize: int
     *     },
     *     struggleWords: list<array{
     *         word: string,
     *         won: bool,
     *         guesses: int,
     *         missCount: int,
     *         duration: string|null
     *     }>
     * }
     */
    public function handle(?User $user = null): array
    {
        if ($user === null) {
            return $this->empty();
        }

        $finished = SpirdlePractice::query()
            ->with('word')
            ->whereBelongsTo($user)
            ->finished()
            ->orderByDesc('finished_at')
            ->get(['id', 'spirdle_word_id', 'won', 'guess_count', 'duration_ms', 'finished_at']);
        $wins = $finished->filter(fn (SpirdlePractice $practice): bool => $practice->won);
        $timed = $finished->filter(fn (SpirdlePractice $practice): bool => $practice->duration_ms !== null);
        $averageDurationMs = $timed->isEmpty()
            ? null
            : (int) round($timed->avg('duration_ms'));
        $missCounts = $this->missCountsByWord($finished);

        return [
            'hasData' => $finished->isNotEmpty(),
            'inProgress' => SpirdlePractice::query()
                ->whereBelongsTo($user)
                ->whereNull('finished_at')
                ->exists(),
            'stats' => [
                'played' => $finished->count(),
                'wins' => $wins->count(),
                'winPercent' => $finished->isEmpty() ? 0 : (int) round(100 * $wins->count() / $finished->count()),
                'averageGuesses' => $wins->isEmpty() ? null : round($wins->avg('guess_count'), 1),
                'averageDurationMs' => $averageDurationMs,
                'averageDurationLabel' => SpirdlePractice::formatDuration($averageDurationMs),
                'missedMoreThanOnce' => $missCounts->filter(fn (int $count): bool => $count > 1)->count(),
            ],
            'pace' => $this->pace($user, $averageDurationMs),
            'struggleWords' => $this->struggleWords($finished, $missCounts),
        ];
    }

    /**
     * @return array{
     *     available: bool,
     *     fasterThanPercent: int|null,
     *     band: string|null,
     *     summary: string|null,
     *     sampleSize: int
     * }
     */
    private function pace(User $user, ?int $averageDurationMs): array
    {
        if ($averageDurationMs === null) {
            return $this->emptyPace();
        }

        $averages = SpirdlePractice::query()
            ->finished()
            ->whereNotNull('duration_ms')
            ->select('user_id', DB::raw('AVG(duration_ms) as avg_ms'))
            ->groupBy('user_id')
            ->pluck('avg_ms', 'user_id')
            ->map(fn (mixed $avg): int => (int) round((float) $avg));

        $sampleSize = $averages->count();

        if ($sampleSize < self::MIN_PEERS_FOR_PACE || ! $averages->has($user->id)) {
            return $this->emptyPace($sampleSize);
        }

        $peersExcludingSelf = $sampleSize - 1;
        $slowerPeers = $averages
            ->except($user->id)
            ->filter(fn (int $avg): bool => $avg > $averageDurationMs)
            ->count();
        $fasterThanPercent = (int) round(100 * $slowerPeers / $peersExcludingSelf);
        [$band, $summary] = $this->paceBand($fasterThanPercent);

        return [
            'available' => true,
            'fasterThanPercent' => $fasterThanPercent,
            'band' => $band,
            'summary' => $summary,
            'sampleSize' => $sampleSize,
        ];
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function paceBand(int $fasterThanPercent): array
    {
        $clamped = max(0, min(100, $fasterThanPercent));
        $fromTop = 100 - $clamped;
        $bandCeiling = max(10, min(100, (int) (ceil($fromTop / 10) * 10)));

        if ($bandCeiling <= 10) {
            return ['top 10%', 'Among the fastest 10% of practice players.'];
        }

        if ($bandCeiling >= 100) {
            return ['last 10%', 'Among the slowest 10% of practice players.'];
        }

        if ($bandCeiling >= 90) {
            return ['last 20%', 'Slower than about 80% of practice players.'];
        }

        return [
            "top {$bandCeiling}%",
            "Faster than {$clamped}% of practice players · top {$bandCeiling}%.",
        ];
    }

    /**
     * @param  Collection<int, SpirdlePractice>  $finished
     * @return Collection<int, int>
     */
    private function missCountsByWord(Collection $finished): Collection
    {
        return $finished
            ->filter(fn (SpirdlePractice $practice): bool => ! $practice->won && $practice->spirdle_word_id !== null)
            ->countBy(fn (SpirdlePractice $practice): int => (int) $practice->spirdle_word_id);
    }

    /**
     * @param  Collection<int, SpirdlePractice>  $finished
     * @param  Collection<int, int>  $missCounts
     * @return list<array{word: string, won: bool, guesses: int, missCount: int, duration: string|null}>
     */
    private function struggleWords(Collection $finished, Collection $missCounts): array
    {
        return $finished
            ->filter(function (SpirdlePractice $practice): bool {
                if ($practice->word?->word === null) {
                    return false;
                }

                return ! $practice->won || $practice->guess_count >= 5;
            })
            ->groupBy(fn (SpirdlePractice $practice): int => (int) $practice->spirdle_word_id)
            ->map(function (Collection $rounds) use ($missCounts): array {
                /** @var SpirdlePractice $worst */
                $worst = $rounds
                    ->sortBy([
                        fn (SpirdlePractice $practice): int => $practice->won ? 1 : 0,
                        fn (SpirdlePractice $practice): int => -$practice->guess_count,
                        fn (SpirdlePractice $practice): int => -((int) $practice->duration_ms),
                        fn (SpirdlePractice $practice): int => -$practice->id,
                    ])
                    ->first();

                $wordId = (int) $worst->spirdle_word_id;

                return [
                    'word' => strtoupper((string) $worst->word?->word),
                    'won' => $worst->won,
                    'guesses' => $worst->guess_count,
                    'missCount' => (int) ($missCounts[$wordId] ?? 0),
                    'duration' => $worst->durationLabel(),
                ];
            })
            ->sortBy([
                fn (array $row): int => -($row['missCount']),
                fn (array $row): int => $row['won'] ? 1 : 0,
                fn (array $row): int => -$row['guesses'],
            ])
            ->take(self::STRUGGLE_LIMIT)
            ->values()
            ->all();
    }

    /**
     * @return array{
     *     hasData: bool,
     *     inProgress: bool,
     *     stats: array{
     *         played: int,
     *         wins: int,
     *         winPercent: int,
     *         averageGuesses: float|null,
     *         averageDurationMs: int|null,
     *         averageDurationLabel: string|null,
     *         missedMoreThanOnce: int
     *     },
     *     pace: array{
     *         available: bool,
     *         fasterThanPercent: int|null,
     *         band: string|null,
     *         summary: string|null,
     *         sampleSize: int
     *     },
     *     struggleWords: list<array{word: string, won: bool, guesses: int, missCount: int, duration: string|null}>
     * }
     */
    private function empty(): array
    {
        return [
            'hasData' => false,
            'inProgress' => false,
            'stats' => [
                'played' => 0,
                'wins' => 0,
                'winPercent' => 0,
                'averageGuesses' => null,
                'averageDurationMs' => null,
                'averageDurationLabel' => null,
                'missedMoreThanOnce' => 0,
            ],
            'pace' => $this->emptyPace(),
            'struggleWords' => [],
        ];
    }

    /**
     * @return array{
     *     available: bool,
     *     fasterThanPercent: int|null,
     *     band: string|null,
     *     summary: string|null,
     *     sampleSize: int
     * }
     */
    private function emptyPace(int $sampleSize = 0): array
    {
        return [
            'available' => false,
            'fasterThanPercent' => null,
            'band' => null,
            'summary' => null,
            'sampleSize' => $sampleSize,
        ];
    }
}
