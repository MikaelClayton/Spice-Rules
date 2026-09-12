<?php

namespace App\Models;

use Database\Factories\ChatReadFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable(['chatable_id', 'chatable_type', 'user_id', 'last_read_message_id'])]
class ChatRead extends Model
{
    /** @use HasFactory<ChatReadFactory> */
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
}
