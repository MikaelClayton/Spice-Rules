<?php

namespace App\Models;

use Database\Factories\GeoguesserChallengeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'geoguesser_id',
    'attempted_at',
    'challenge_token',
    'game_token',
    'map_name',
    'total_score',
    'geoguesser_guid',
    'total_distance',
    'total_steps_count',
    'progress',
])]
class GeoguesserChallenge extends Model
{
    /** @use HasFactory<GeoguesserChallengeFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'attempted_at' => 'datetime',
            'total_score' => 'integer',
            'total_distance' => 'integer',
            'total_steps_count' => 'integer',
            'progress' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Geoguesser, $this>
     */
    public function geoguesser(): BelongsTo
    {
        return $this->belongsTo(Geoguesser::class);
    }

    /**
     * @return HasMany<GeoguesserRound, $this>
     */
    public function rounds(): HasMany
    {
        return $this->hasMany(GeoguesserRound::class)->orderBy('round_number');
    }
}
