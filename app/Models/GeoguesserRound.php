<?php

namespace App\Models;

use Database\Factories\GeoguesserRoundFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'geoguesser_challenge_id',
    'round_number',
    'actual_lat',
    'actual_lng',
    'guess_lat',
    'guess_lng',
    'score',
    'percentage',
    'time',
    'steps_count',
    'distance_in_meters',
    'timed_out',
    'timed_out_with_guess',
    'skipped_round',
    'heading',
    'pitch',
    'zoom',
    'pano_id',
    'country_code',
    'guess_country_code',
    'started_at',
])]
class GeoguesserRound extends Model
{
    /** @use HasFactory<GeoguesserRoundFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'round_number' => 'integer',
            'actual_lat' => 'float',
            'actual_lng' => 'float',
            'guess_lat' => 'float',
            'guess_lng' => 'float',
            'score' => 'integer',
            'percentage' => 'float',
            'time' => 'integer',
            'steps_count' => 'integer',
            'distance_in_meters' => 'integer',
            'timed_out' => 'boolean',
            'timed_out_with_guess' => 'boolean',
            'skipped_round' => 'boolean',
            'heading' => 'float',
            'pitch' => 'float',
            'zoom' => 'integer',
            'started_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<GeoguesserChallenge, $this>
     */
    public function challenge(): BelongsTo
    {
        return $this->belongsTo(GeoguesserChallenge::class, 'geoguesser_challenge_id');
    }
}
