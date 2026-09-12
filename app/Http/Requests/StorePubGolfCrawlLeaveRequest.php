<?php

namespace App\Http\Requests;

use App\Models\PubGolfCrawl;
use Illuminate\Foundation\Http\FormRequest;

class StorePubGolfCrawlLeaveRequest extends FormRequest
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
}
