<?php

namespace App\Models;

use Database\Factories\SpirdlePuzzleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['spirdle_word_id', 'play_date'])]
class SpirdlePuzzle extends Model
{
    /** @use HasFactory<SpirdlePuzzleFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'play_date' => 'date',
        ];
    }

    /**
     * @return BelongsTo<SpirdleWord, $this>
     */
    public function word(): BelongsTo
    {
        return $this->belongsTo(SpirdleWord::class, 'spirdle_word_id');
    }

    /**
     * @return HasMany<SpirdlePlay, $this>
     */
    public function plays(): HasMany
    {
        return $this->hasMany(SpirdlePlay::class);
    }

    public function solution(): string
    {
        return (string) $this->word?->word;
    }
}
