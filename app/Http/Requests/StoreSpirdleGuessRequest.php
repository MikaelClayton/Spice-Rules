<?php

namespace App\Http\Requests;

use App\Models\SpirdlePlay;
use Illuminate\Foundation\Http\FormRequest;

class StoreSpirdleGuessRequest extends FormRequest
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
            'word' => ['required', 'string', 'size:'.SpirdlePlay::WORD_LENGTH, 'regex:/^[a-z]+$/'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'word.required' => 'Type a 5-letter word.',
            'word.size' => 'Use 5 letters.',
            'word.regex' => 'Letters only.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $word = $this->input('word');

        if (is_string($word)) {
            $this->merge(['word' => strtolower(trim($word))]);
        }
    }

    public function word(): string
    {
        return $this->validated('word');
    }
}
