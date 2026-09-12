<?php

namespace App\Http\Requests;

use App\Models\PubGolfCrawl;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StorePubGolfDrinkUndoRequest extends FormRequest
{
    public function authorize(): bool
    {
        $crawl = $this->route('pubGolfCrawl');

        return $crawl instanceof PubGolfCrawl
            && $this->user() !== null
            && $crawl->hasParticipant($this->user());
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $crawl = $this->route('pubGolfCrawl');

                if ($crawl instanceof PubGolfCrawl && ! $crawl->isActiveParticipant($this->user())) {
                    $validator->errors()->add('drink', 'You already called it on this crawl.');
                }
            },
        ];
    }
}
