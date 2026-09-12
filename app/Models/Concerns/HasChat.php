<?php

namespace App\Models\Concerns;

use App\Models\ChatMessage;
use App\Models\ChatRead;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasChat
{
    /**
     * @return MorphMany<ChatMessage, $this>
     */
    public function chatMessages(): MorphMany
    {
        return $this->morphMany(ChatMessage::class, 'chatable');
    }

    /**
     * @return MorphMany<ChatRead, $this>
     */
    public function chatReads(): MorphMany
    {
        return $this->morphMany(ChatRead::class, 'chatable');
    }
}
