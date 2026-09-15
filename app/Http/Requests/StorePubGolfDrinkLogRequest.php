<?php

namespace App\Http\Requests;

use App\Models\PubGolfCrawl;
use App\Services\PubGolf\PubGolfListedDrink;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use RuntimeException;

class StorePubGolfDrinkLogRequest extends FormRequest
{
    public function authorize(): bool
    {
        $crawl = $this->route('pubGolfCrawl');

        return $crawl instanceof PubGolfCrawl
            && $this->user() !== null
            && $crawl->hasParticipant($this->user());
    }

    public function latitude(): ?float
    {
        $latitude = $this->validated('latitude');

        return is_numeric($latitude) ? (float) $latitude : null;
    }

    public function longitude(): ?float
    {
        $longitude = $this->validated('longitude');

        return is_numeric($longitude) ? (float) $longitude : null;
    }

    protected function prepareForValidation(): void
    {
        $drinkId = PubGolfListedDrink::customId((string) $this->input('drink'));

        if ($drinkId !== null) {
            $this->merge(['drink' => $drinkId]);
        }

        [$latitude, $longitude] = $this->normalizedCoordinates();

        $this->merge([
            'latitude' => $latitude,
            'longitude' => $longitude,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'drink' => ['required', 'integer', Rule::exists('pub_golf_custom_drinks', 'id')],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'drink.required' => 'Pick a drink.',
            'drink.integer' => 'Pick a drink from the list.',
            'drink.exists' => 'Pick a drink from the list.',
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $crawl = $this->route('pubGolfCrawl');

                if ($crawl instanceof PubGolfCrawl && ! $crawl->isActiveParticipant($this->user())) {
                    $validator->errors()->add('drink', 'You already called it on this crawl.');

                    return;
                }

                $listed = PubGolfListedDrink::fromKey((string) $this->input('drink'));

                if ($listed === null || ! $listed->isListed) {
                    $validator->errors()->add('drink', 'Pick a drink from the list.');
                }
            },
        ];
    }

    public function listedDrink(): PubGolfListedDrink
    {
        $listed = PubGolfListedDrink::fromKey((string) $this->validated('drink'));

        if ($listed === null || ! $listed->isListed) {
            throw new RuntimeException('Validated drink was not on the list.');
        }

        return $listed;
    }

    /**
     * @return array{0: ?float, 1: ?float}
     */
    private function normalizedCoordinates(): array
    {
        $latitude = $this->input('latitude');
        $longitude = $this->input('longitude');

        if (! is_numeric($latitude) || ! is_numeric($longitude)) {
            return [null, null];
        }

        $latitude = (float) $latitude;
        $longitude = (float) $longitude;

        if ($latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180) {
            return [null, null];
        }

        return [$latitude, $longitude];
    }
}
