<?php

namespace App\Http\Requests;

use App\Models\PubGolfCrawl;
use Illuminate\Foundation\Http\FormRequest;

class StorePubGolfChatReadRequest extends FormRequest
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
        return [
            'last_read_message_id' => ['nullable', 'integer', 'min:0'],
        ];
    }

    public function lastReadMessageId(): ?int
    {
        $id = $this->validated('last_read_message_id');

        return is_numeric($id) ? (int) $id : null;
    }
}
