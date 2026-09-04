<?php

namespace App\Models;

use Database\Factories\GeoguesserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'user_id',
    'username',
    'ncfa',
    'daily_challenge_progress',
    'daily_challenge_streak',
    'daily_challenge_current_streak',
    'is_active',
])]
#[Hidden(['ncfa'])]
class Geoguesser extends Model
{
    /** @use HasFactory<GeoguesserFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'daily_challenge_progress' => 'integer',
            'daily_challenge_streak' => 'integer',
            'daily_challenge_current_streak' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<GeoguesserChallenge, $this>
     */
    public function challenges(): HasMany
    {
        return $this->hasMany(GeoguesserChallenge::class);
    }

    /**
     * @param  array<string, mixed>  $profile
     */
    public function applyFromProfile(array $profile): void
    {
        $user = is_array($profile['user'] ?? null) ? $profile['user'] : $profile;

        $this->fill([
            'username' => $user['nick'] ?? $this->username,
            'daily_challenge_progress' => $user['dailyChallengeProgress'] ?? $this->daily_challenge_progress,
        ]);
    }
}
