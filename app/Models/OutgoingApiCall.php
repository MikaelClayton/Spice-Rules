<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'source',
    'method',
    'url',
    'status_code',
    'succeeded',
    'duration_ms',
    'error_message',
    'response',
    'cron_run_id',
    'geoguesser_id',
])]
class OutgoingApiCall extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status_code' => 'integer',
            'succeeded' => 'boolean',
            'duration_ms' => 'integer',
            'response' => 'array',
        ];
    }

    /**
     * @return BelongsTo<CronRun, $this>
     */
    public function cronRun(): BelongsTo
    {
        return $this->belongsTo(CronRun::class);
    }

    /**
     * @return BelongsTo<Geoguesser, $this>
     */
    public function geoguesser(): BelongsTo
    {
        return $this->belongsTo(Geoguesser::class);
    }
}
