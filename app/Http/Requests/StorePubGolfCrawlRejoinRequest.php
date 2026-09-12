<?php

namespace App\Http\Requests;

use App\Models\PubGolfCrawl;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StorePubGolfCrawlRejoinRequest extends FormRequest
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
                $user = $this->user();

                if (! $crawl instanceof PubGolfCrawl || $user === null) {
                    return;
                }

                if (! $crawl->isOpen()) {
                    $validator->errors()->add('crawl', 'That crawl has already wrapped up.');
                }
            },
        ];
    }
}
