<?php

namespace App\Models;

use App\Models\Concerns\HasSpirdleRound;
use Database\Factories\SpirdlePlayFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
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

    use HasSpirdleRound;

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

    public function roundSolution(): ?string
    {
        return $this->puzzle?->solution();
    }

    public function weeklyPoints(): int
    {
        if (! $this->won) {
            return 0;
        }

        return max(1, self::MAX_GUESSES + 1 - $this->guess_count);
    }
}
