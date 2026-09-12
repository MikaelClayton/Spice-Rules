<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePubGolfCrawlJoinRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'size:6'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'code.required' => 'Enter the crawl code.',
            'code.size' => 'Crawl codes are 6 characters.',
        ];
    }

    public function joinCode(): string
    {
        return strtoupper((string) $this->validated('code'));
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->code)) {
            $this->merge([
                'code' => strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', $this->code)),
            ]);
        }
    }
}
