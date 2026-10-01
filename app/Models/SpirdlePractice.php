<?php

namespace App\Models;

use App\Models\Concerns\HasSpirdleRound;
use Database\Factories\SpirdlePracticeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'spirdle_word_id',
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
class SpirdlePractice extends Model
{
    public const MAX_GUESSES = 6;

    public const WORD_LENGTH = 5;

    /** @use HasFactory<SpirdlePracticeFactory> */
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
     * @return BelongsTo<SpirdleWord, $this>
     */
    public function word(): BelongsTo
    {
        return $this->belongsTo(SpirdleWord::class, 'spirdle_word_id');
    }

    public function roundSolution(): ?string
    {
        return $this->word?->word;
    }
}
