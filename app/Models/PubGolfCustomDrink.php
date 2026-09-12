<?php

namespace App\Models;

use App\Enums\PubGolfDrinkCategory;
use Database\Factories\PubGolfCustomDrinkFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

#[Fillable(['user_id', 'name', 'category', 'photo_path', 'removed_at'])]
class PubGolfCustomDrink extends Model
{
    /** @use HasFactory<PubGolfCustomDrinkFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category' => PubGolfDrinkCategory::class,
            'removed_at' => 'datetime',
        ];
    }

    #[Scope]
    protected function active(Builder $query): Builder
    {
        return $query->whereNull('removed_at');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<PubGolfDrinkLog, $this>
     */
    public function drinkLogs(): HasMany
    {
        return $this->hasMany(PubGolfDrinkLog::class, 'drink_id');
    }

    public function photoUrl(): ?string
    {
        if (! is_string($this->photo_path) || $this->photo_path === '') {
            return null;
        }

        return Storage::disk((string) config('pub-golf.photo_disk'))->url($this->photo_path);
    }
}
