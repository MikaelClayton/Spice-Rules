<?php

namespace App\Models;

use Database\Factories\ChatMessageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Storage;

#[Fillable(['chatable_id', 'chatable_type', 'user_id', 'body', 'photo_path'])]
class ChatMessage extends Model
{
    /** @use HasFactory<ChatMessageFactory> */
    use HasFactory;

    /**
     * @return MorphTo<Model, $this>
     */
    public function chatable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<ChatMention, $this>
     */
    public function mentions(): HasMany
    {
        return $this->hasMany(ChatMention::class);
    }

    public function photoUrl(): ?string
    {
        if (! is_string($this->photo_path) || $this->photo_path === '') {
            return null;
        }

        return Storage::disk((string) config('chat.photo_disk'))->url($this->photo_path);
    }
}
