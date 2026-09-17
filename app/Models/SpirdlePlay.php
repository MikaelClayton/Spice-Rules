<?php

namespace App\Models;

use Database\Factories\SpirdlePlayFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'spirdle_puzzle_id',
    'guesses',
    'guess_count',
    'invalid_word_count',
    'duration_ms',
    'accumulated_ms',
    'won',
    'started_at',
    'running_since',
    'finished_at',
])]
class SpirdlePlay extends Model
{
    public const MAX_GUESSES = 6;

    public const WORD_LENGTH = 5;

    /** @use HasFactory<SpirdlePlayFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'guesses' => 'array',
            'guess_count' => 'integer',
            'invalid_word_count' => 'integer',
            'duration_ms' => 'integer',
            'accumulated_ms' => 'integer',
            'won' => 'boolean',
            'started_at' => 'datetime',
            'running_since' => 'datetime',
            'finished_at' => 'datetime',
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
     * @return BelongsTo<SpirdlePuzzle, $this>
     */
    public function puzzle(): BelongsTo
    {
        return $this->belongsTo(SpirdlePuzzle::class, 'spirdle_puzzle_id');
    }

    #[Scope]
    protected function finished(Builder $query): Builder
    {
        return $query->whereNotNull('finished_at');
    }

    public function isFinished(): bool
    {
        return $this->finished_at !== null;
    }

    public function weeklyPoints(): int
    {
        if (! $this->won) {
            return 0;
        }

        return max(1, self::MAX_GUESSES + 1 - $this->guess_count);
    }

    public function isPaused(): bool
    {
        return ! $this->isFinished() && $this->running_since === null;
    }

    public function elapsedMilliseconds(): int
    {
        if ($this->duration_ms !== null && $this->isFinished()) {
            return $this->duration_ms;
        }

        $elapsed = (int) $this->accumulated_ms;

        if ($this->running_since !== null) {
            $elapsed += max(0, (int) $this->running_since->diffInMilliseconds(now()));
        }

        return max(0, $elapsed);
    }

    public function durationLabel(?int $milliseconds = null): ?string
    {
        $milliseconds ??= $this->isFinished() ? $this->duration_ms : $this->elapsedMilliseconds();

        if ($milliseconds === null) {
            return null;
        }

        $seconds = intdiv($milliseconds, 1000);

        if ($seconds < 60) {
            return $seconds.'s';
        }

        $minutes = intdiv($seconds, 60);
        $remainder = $seconds % 60;

        return $minutes.':'.str_pad((string) $remainder, 2, '0', STR_PAD_LEFT);
    }

    /**
     * @return array{
     *     guesses: list<array{word: string, tiles: list<string>}>,
     *     guessCount: int,
     *     invalidWordCount: int,
     *     maxGuesses: int,
     *     wordLength: int,
     *     startedAt: string|null,
     *     elapsedMs: int,
     *     paused: bool,
     *     finished: bool,
     *     won: bool,
     *     durationMs: int|null,
     *     durationLabel: string|null,
     *     solution: string|null
     * }
     */
    public function gameState(): array
    {
        $finished = $this->isFinished();
        $elapsed = $this->elapsedMilliseconds();

        return [
            'guesses' => $this->normalizedGuesses(),
            'guessCount' => $this->guess_count,
            'invalidWordCount' => $this->invalid_word_count,
            'maxGuesses' => self::MAX_GUESSES,
            'wordLength' => self::WORD_LENGTH,
            'startedAt' => $this->started_at?->toIso8601String(),
            'elapsedMs' => $elapsed,
            'paused' => $this->isPaused(),
            'finished' => $finished,
            'won' => $this->won,
            'durationMs' => $this->duration_ms,
            'durationLabel' => $this->durationLabel($elapsed),
            'solution' => $finished ? $this->puzzle?->solution() : null,
        ];
    }

    /**
     * @return list<array{word: string, tiles: list<string>}>
     */
    public function normalizedGuesses(): array
    {
        $guesses = is_array($this->guesses) ? $this->guesses : [];

        return array_values(array_map(function (mixed $guess): array {
            $row = is_array($guess) ? $guess : [];
            $tiles = is_array($row['tiles'] ?? null) ? array_values($row['tiles']) : [];

            return [
                'word' => strtolower((string) ($row['word'] ?? '')),
                'tiles' => array_map(strval(...), $tiles),
            ];
        }, $guesses));
    }
}
