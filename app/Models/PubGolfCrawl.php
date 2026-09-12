<?php

namespace App\Models;

use App\Contracts\Chatable;
use App\Models\Concerns\HasChat;
use Database\Factories\PubGolfCrawlFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

#[Fillable(['user_id', 'name', 'join_code', 'started_at', 'ended_at'])]
class PubGolfCrawl extends Model implements Chatable
{
    /** @use HasFactory<PubGolfCrawlFactory> */
    use HasChat, HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    #[Scope]
    protected function open(Builder $query): Builder
    {
        return $query->whereNull('ended_at');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function starter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @return HasMany<PubGolfParticipant, $this>
     */
    public function participants(): HasMany
    {
        return $this->hasMany(PubGolfParticipant::class);
    }

    /**
     * @return HasMany<PubGolfDrinkLog, $this>
     */
    public function drinkLogs(): HasMany
    {
        return $this->hasMany(PubGolfDrinkLog::class);
    }

    public function isOpen(): bool
    {
        return $this->ended_at === null;
    }

    public function participantFor(?User $user): ?PubGolfParticipant
    {
        if ($user === null) {
            return null;
        }

        if ($this->relationLoaded('participants')) {
            return $this->participants->firstWhere('user_id', $user->id);
        }

        return $this->participants()->where('user_id', $user->id)->first();
    }

    public function hasParticipant(?User $user): bool
    {
        return $this->participantFor($user) !== null;
    }

    public function isActiveParticipant(?User $user): bool
    {
        return $this->participantFor($user)?->isActive() === true;
    }

    public static function currentFor(User $user): ?self
    {
        return self::query()
            ->open()
            ->whereHas(
                'participants',
                fn (Builder $query) => $query->where('user_id', $user->id)->whereNull('left_at'),
            )
            ->orderByDesc('started_at')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * @return Collection<int, User>
     */
    public function chatParticipants(): Collection
    {
        $this->loadMissing('participants.user');

        return $this->participants
            ->map(fn (PubGolfParticipant $participant) => $participant->user)
            ->filter()
            ->unique('id')
            ->values();
    }

    public function canViewChat(?User $user): bool
    {
        return $this->hasParticipant($user);
    }

    public function canSendChat(?User $user): bool
    {
        return $this->hasParticipant($user) && $this->isOpen();
    }

    public function chatTitle(): string
    {
        return $this->name;
    }

    public function chatUrl(): string
    {
        return route('pub-golf.show', $this);
    }

    public function chatDeniedMessage(): string
    {
        return 'You are not on this crawl.';
    }

    public function chatClosedMessage(): string
    {
        return 'That crawl has already wrapped up.';
    }
}
