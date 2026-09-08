<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'color'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * @return HasOne<Geoguesser, $this>
     */
    public function geoguesser(): HasOne
    {
        return $this->hasOne(Geoguesser::class);
    }

    /**
     * @return HasMany<DeviceToken, $this>
     */
    public function deviceTokens(): HasMany
    {
        return $this->hasMany(DeviceToken::class);
    }

    /**
     * @return HasManyThrough<GeoguesserChallenge, Geoguesser, $this>
     */
    public function geoguesserChallenges(): HasManyThrough
    {
        return $this->hasManyThrough(GeoguesserChallenge::class, Geoguesser::class);
    }

    /**
     * @return BelongsToMany<WicketGroup, $this>
     */
    public function wicketGroups(): BelongsToMany
    {
        return $this->belongsToMany(WicketGroup::class)
            ->withPivot('role')
            ->withTimestamps();
    }

    /**
     * @return HasMany<WicketGroup, $this>
     */
    public function ownedWicketGroups(): HasMany
    {
        return $this->hasMany(WicketGroup::class);
    }

    public function boardColor(): string
    {
        if (is_string($this->color) && preg_match('/^#[0-9A-Fa-f]{6}$/', $this->color) === 1) {
            return strtoupper($this->color);
        }

        return self::fallbackColor($this->id);
    }

    public function canBrowseGeoguessrChallenges(): bool
    {
        return $this->isAdmin();
    }

    public function isAdmin(): bool
    {
        return strcasecmp((string) $this->email, 'mikaelclayton@gmail.com') === 0;
    }

    public static function fallbackColor(int $id): string
    {
        $palette = ['#D82820', '#FEC523', '#2A9D8F', '#E85D04', '#283030', '#9B2226', '#F4A261'];

        return $palette[$id % count($palette)];
    }
}
