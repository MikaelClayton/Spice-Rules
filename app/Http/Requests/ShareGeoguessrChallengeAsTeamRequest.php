<?php

namespace App\Http\Requests;

use App\Models\GeoguesserChallenge;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ShareGeoguessrChallengeAsTeamRequest extends FormRequest
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
            'challenge_id' => ['required', 'integer', 'exists:geoguesser_challenges,id'],
            'geoguesser_ids' => ['required', 'array', 'min:1'],
            'geoguesser_ids.*' => [
                'integer',
                'distinct',
                Rule::exists('geoguessers', 'id')->where('is_active', true),
            ],
        ];
    }

    /**
     * @return list<int>
     */
    public function geoguesserIds(): array
    {
        return array_values(array_unique(array_map(
            intval(...),
            $this->validated('geoguesser_ids'),
        )));
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $source = GeoguesserChallenge::query()
                    ->with('geoguesser.user')
                    ->find($this->integer('challenge_id'));

                if ($source === null) {
                    return;
                }

                $ids = collect($this->input('geoguesser_ids', []))
                    ->map(fn ($id): int => (int) $id)
                    ->unique()
                    ->values();

                if ($ids->contains((int) $source->geoguesser_id)) {
                    $validator->errors()->add(
                        'geoguesser_ids',
                        'Pick someone other than the player who logged this challenge.',
                    );

                    return;
                }

                $date = $source->attempted_at?->toDateString();

                if ($date === null) {
                    $validator->errors()->add('challenge_id', 'This challenge has no date to share.');

                    return;
                }

                $alreadyPlayed = GeoguesserChallenge::query()
                    ->with('geoguesser.user')
                    ->whereIn('geoguesser_id', $ids->all())
                    ->whereDate('attempted_at', $date)
                    ->get()
                    ->unique('geoguesser_id');

                if ($alreadyPlayed->isEmpty()) {
                    return;
                }

                $names = $alreadyPlayed
                    ->map(fn (GeoguesserChallenge $challenge): string => $challenge->geoguesser?->displayName() ?? 'That player')
                    ->unique()
                    ->values();

                $validator->errors()->add(
                    'geoguesser_ids',
                    $names->count() === 1
                        ? "{$names->first()} already has a challenge for that day."
                        : $names->join(', ').' already have a challenge for that day.',
                );
            },
        ];
    }
}
