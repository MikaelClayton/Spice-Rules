<?php

namespace App\Models;

use Database\Factories\FitIshSessionZoneFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

#[Fillable([
    'fit_ish_session_id',
    'zone_number',
    'name',
    'description',
    'color_hex',
    'min_percentage',
    'max_percentage',
    'min_bpm',
    'max_bpm',
    'bpm_label',
    'duration_seconds',
    'duration_label',
    'percentage_value',
    'percentage_label',
])]
class FitIshSessionZone extends Model
{
    /** @use HasFactory<FitIshSessionZoneFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'zone_number' => 'integer',
            'min_percentage' => 'decimal:1',
            'max_percentage' => 'decimal:1',
            'min_bpm' => 'integer',
            'max_bpm' => 'integer',
            'duration_seconds' => 'integer',
            'percentage_value' => 'decimal:1',
        ];
    }

    /**
     * @return BelongsTo<FitIshSession, $this>
     */
    public function session(): BelongsTo
    {
        return $this->belongsTo(FitIshSession::class, 'fit_ish_session_id');
    }

    public function isHardEffort(): bool
    {
        return $this->zone_number >= 4;
    }

    public function shortName(): string
    {
        $name = trim((string) $this->name);

        if ($name === '') {
            return 'Zone '.$this->zone_number;
        }

        if (! str_contains($name, '/')) {
            return $name;
        }

        $short = trim(Str::afterLast($name, '/'));

        return $short !== '' ? $short : $name;
    }

    public function swatch(): string
    {
        $hex = ltrim((string) $this->color_hex, '#');

        if (preg_match('/^[0-9A-Fa-f]{6}$/', $hex) !== 1) {
            return '#888888';
        }

        return '#'.$hex;
    }
}
