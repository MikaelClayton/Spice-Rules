<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BrowseGeoguessrChallengesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canBrowseGeoguessrChallenges() === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'page' => ['sometimes', 'integer', 'min:1'],
            'player' => ['sometimes', 'nullable', 'string'],
        ];
    }

    public function pageNumber(): int
    {
        $page = $this->integer('page');

        return $page > 0 ? $page : 1;
    }

    public function playerId(): ?int
    {
        $player = $this->string('player')->toString();

        if ($player === '' || $player === 'all' || ! ctype_digit($player)) {
            return null;
        }

        return (int) $player;
    }
}
