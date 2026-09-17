<?php

namespace App\Models;

use Database\Factories\SpirdleWordFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['word', 'is_answer'])]
class SpirdleWord extends Model
{
    /** @use HasFactory<SpirdleWordFactory> */
    use HasFactory;

    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_answer' => 'boolean',
        ];
    }

    /**
     * @return HasMany<SpirdlePuzzle, $this>
     */
    public function puzzles(): HasMany
    {
        return $this->hasMany(SpirdlePuzzle::class);
    }

    #[Scope]
    protected function answers(Builder $query): Builder
    {
        return $query->where('is_answer', true);
    }
}
