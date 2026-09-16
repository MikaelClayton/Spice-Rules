<?php

namespace App\Models;

use Database\Factories\FitIshStudioFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['external_id', 'name', 'code', 'timezone', 'is_loaner'])]
class FitIshStudio extends Model
{
    /** @use HasFactory<FitIshStudioFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'external_id' => 'integer',
            'is_loaner' => 'boolean',
        ];
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }

    /**
     * @return HasMany<FitIshSession, $this>
     */
    public function sessions(): HasMany
    {
        return $this->hasMany(FitIshSession::class);
    }

    public function label(): string
    {
        return $this->name.' ('.$this->code.')';
    }
}
