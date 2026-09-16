<?php

namespace App\Models;

use Database\Factories\FitIshProfileSummaryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'timeframe_key',
    'timeframe_id',
    'timeframe_name',
    'number_of_days',
    'session_count',
    'average_points',
    'average_calories',
    'max_points',
])]
class FitIshProfileSummary extends Model
{
    /** @use HasFactory<FitIshProfileSummaryFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'timeframe_id' => 'integer',
            'number_of_days' => 'integer',
            'session_count' => 'integer',
            'average_points' => 'decimal:2',
            'average_calories' => 'integer',
            'max_points' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
