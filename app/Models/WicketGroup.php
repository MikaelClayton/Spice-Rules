<?php

namespace App\Models;

use Database\Factories\WicketGroupFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['user_id', 'name'])]
class WicketGroup extends Model
{
    /** @use HasFactory<WicketGroupFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }

    /**
     * @return HasMany<WicketFine, $this>
     */
    public function fines(): HasMany
    {
        return $this->hasMany(WicketFine::class);
    }

    /**
     * @return HasMany<WicketSipLog, $this>
     */
    public function sipLogs(): HasMany
    {
        return $this->hasMany(WicketSipLog::class);
    }

    public function hasMember(?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        if ($this->relationLoaded('users')) {
            return $this->users->contains($user);
        }

        return $this->users()->whereKey($user->id)->exists();
    }

    public function isOwnedBy(?User $user): bool
    {
        return $user !== null && $this->user_id === $user->id;
    }
}
