<?php

namespace App\Models;

use Database\Factories\FitIshSessionGraphPointFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'fit_ish_session_id',
    'minute',
    'type',
    'bpm_min',
    'bpm_max',
])]
class FitIshSessionGraphPoint extends Model
{
    /** @use HasFactory<FitIshSessionGraphPointFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'minute' => 'integer',
            'bpm_min' => 'integer',
            'bpm_max' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<FitIshSession, $this>
     */
    public function session(): BelongsTo
    {
        return $this->belongsTo(FitIshSession::class, 'fit_ish_session_id');
    }

    public function hasRecording(): bool
    {
        return $this->type === 'recordedBpm' && $this->bpm_max !== null;
    }
}
