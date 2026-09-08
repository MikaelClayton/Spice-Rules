<?php

namespace App\Models;

use App\Enums\WicketGroupRole;
use Database\Factories\WicketGroupFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['user_id', 'name', 'is_tournament'])]
class WicketGroup extends Model
{
    /** @use HasFactory<WicketGroupFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_tournament' => 'boolean',
        ];
    }

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
        return $this->belongsToMany(User::class)
            ->withPivot('role')
            ->withTimestamps();
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

    public function isTournament(): bool
    {
        return $this->is_tournament === true;
    }

    public function memberRole(?User $user): WicketGroupRole
    {
        if ($user === null || ! $this->hasMember($user)) {
            return WicketGroupRole::Member;
        }

        $value = $this->relationLoaded('users')
            ? $this->users->firstWhere('id', $user->id)?->pivot?->role
            : $this->users()->whereKey($user->id)->first()?->pivot?->role;

        return WicketGroupRole::tryFrom((string) $value) ?? WicketGroupRole::Member;
    }

    public function isFinesMaster(?User $user): bool
    {
        return $this->memberRole($user)->isFinesMaster();
    }

    public function hidesOwnFinesFrom(?User $user): bool
    {
        return $user !== null && $this->isTournament();
    }
}
