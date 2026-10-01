<?php

namespace App\Models;

use App\Enums\WicketFineType;
use Database\Factories\WicketFineFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'wicket_group_id',
    'issued_by_user_id',
    'issued_to_user_id',
    'reason',
    'type',
    'sips_owed',
    'sips_completed',
    'accumulated_from',
    'completed_at',
])]
class WicketFine extends Model
{
    public const ACCUMULATION_REASON = 'Your sips reached 8, so the system gave you a down down for accumulation.';

    /** @use HasFactory<WicketFineFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => WicketFineType::class,
            'sips_owed' => 'integer',
            'sips_completed' => 'integer',
            'accumulated_from' => 'array',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<WicketGroup, $this>
     */
    public function wicketGroup(): BelongsTo
    {
        return $this->belongsTo(WicketGroup::class, 'wicket_group_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function issuedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by_user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function issuedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_to_user_id');
    }

    public function remainingSips(): int
    {
        return max(0, $this->sips_owed - $this->sips_completed);
    }

    public function displayLabel(): string
    {
        if ($this->type === WicketFineType::Sips) {
            return $this->sips_owed === 1 ? '1 sip' : $this->sips_owed.' sips';
        }

        return $this->type->label();
    }

    public function displayReason(): string
    {
        if ($this->isAccumulationDownDown()) {
            return self::ACCUMULATION_REASON;
        }

        return $this->reason;
    }

    /**
     * Lines explaining which sip fines added up to this accumulation down down.
     *
     * @return list<string>
     */
    public function accumulationSummary(): array
    {
        if (! $this->isAccumulationDownDown() || ! is_array($this->accumulated_from)) {
            return [];
        }

        return array_values(array_map(function (array $part): string {
            $sips = (int) ($part['sips'] ?? 0);
            $owed = (int) ($part['sips_owed'] ?? $sips);
            $amount = $sips < $owed
                ? $sips.' of '.$owed.' sips'
                : ($sips === 1 ? '1 sip' : $sips.' sips');

            return $amount.' for "'.($part['reason'] ?? '').'" from '.($part['issued_by'] ?? 'Someone');
        }, $this->accumulated_from));
    }

    public function isOutstanding(): bool
    {
        return $this->completed_at === null;
    }

    public function isAccumulationDownDown(): bool
    {
        return $this->type === WicketFineType::DownDown
            && in_array($this->reason, [self::ACCUMULATION_REASON, '8 sips'], true);
    }
}
