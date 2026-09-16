<?php

namespace App\Models;

use Database\Factories\FitIshWorkoutFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

#[Fillable(['name', 'display_name', 'type', 'logo_path', 'logo_url', 'description'])]
class FitIshWorkout extends Model
{
    /** @use HasFactory<FitIshWorkoutFactory> */
    use HasFactory;

    /**
     * @return HasMany<FitIshSession, $this>
     */
    public function sessions(): HasMany
    {
        return $this->hasMany(FitIshSession::class);
    }

    public function logoUrl(): ?string
    {
        if (filled($this->logo_path)) {
            $stored = Storage::disk((string) config('fit-ish.logo_disk', 'public'))->url($this->logo_path);
            $path = parse_url($stored, PHP_URL_PATH);

            return url(is_string($path) && $path !== '' ? $path : '/storage/'.$this->logo_path);
        }

        return filled($this->logo_url) ? $this->logo_url : null;
    }
}
