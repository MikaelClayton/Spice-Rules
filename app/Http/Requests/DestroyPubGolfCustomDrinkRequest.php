<?php

namespace App\Http\Requests;

use App\Models\PubGolfCrawl;
use App\Models\PubGolfCustomDrink;
use Illuminate\Foundation\Http\FormRequest;

class DestroyPubGolfCustomDrinkRequest extends FormRequest
{
    public function authorize(): bool
    {
        $crawl = $this->route('pubGolfCrawl');
        $drink = $this->route('pubGolfCustomDrink');

        return $crawl instanceof PubGolfCrawl
            && $drink instanceof PubGolfCustomDrink
            && $this->user() !== null
            && $crawl->isActiveParticipant($this->user())
            && $drink->user_id === $this->user()->id
            && $drink->removed_at === null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
