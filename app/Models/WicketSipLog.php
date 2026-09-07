<?php

namespace App\Models;

use Database\Factories\WicketSipLogFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['wicket_group_id', 'user_id', 'sips'])]
class WicketSipLog extends Model
{
    /** @use HasFactory<WicketSipLogFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sips' => 'integer',
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
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
