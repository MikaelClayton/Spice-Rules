<?php

namespace App\Models;

use Database\Factories\PubGolfParticipantFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

#[Fillable(['pub_golf_crawl_id', 'user_id', 'joined_at', 'left_at'])]
class PubGolfParticipant extends Model
{
    /** @use HasFactory<PubGolfParticipantFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'joined_at' => 'datetime',
            'left_at' => 'datetime',
        ];
    }

    #[Scope]
    protected function active(Builder $query): Builder
    {
        return $query->whereNull('left_at');
    }

    /**
     * @return BelongsTo<PubGolfCrawl, $this>
     */
    public function crawl(): BelongsTo
    {
        return $this->belongsTo(PubGolfCrawl::class, 'pub_golf_crawl_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isActive(): bool
    {
        return $this->left_at === null;
    }

    public function durationSeconds(?Carbon $now = null): int
    {
        $end = $this->left_at ?? $now ?? now();

        return max(0, (int) $this->joined_at->diffInSeconds($end));
    }
}
