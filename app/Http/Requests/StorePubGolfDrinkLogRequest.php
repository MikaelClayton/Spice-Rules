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

    protected function prepareForValidation(): void
    {
        $drinkId = PubGolfListedDrink::customId((string) $this->input('drink'));

        if ($drinkId !== null) {
            $this->merge(['drink' => $drinkId]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'drink' => ['required', 'integer', Rule::exists('pub_golf_custom_drinks', 'id')],
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
}
