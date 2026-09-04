<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'command',
    'status',
    'profiles_synced',
    'duration_ms',
    'error_message',
    'started_at',
    'finished_at',
])]
class CronRun extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'profiles_synced' => 'integer',
            'duration_ms' => 'integer',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<OutgoingApiCall, $this>
     */
    public function outgoingApiCalls(): HasMany
    {
        return $this->hasMany(OutgoingApiCall::class);
    }
}
