<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateFitIshSettingsRequest extends FormRequest
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
            'fit_ish_user_id' => [
                'nullable',
                'string',
                'max:32',
                'regex:/^\d+$/',
                Rule::unique('users', 'fit_ish_user_id')->ignore($this->user()?->id),
            ],
            'fit_ish_serial' => ['nullable', 'string', 'max:64'],
            'studio_ids' => ['nullable', 'array'],
            'studio_ids.*' => ['integer', 'exists:fit_ish_studios,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'fit_ish_user_id.regex' => 'Lionheart user IDs are numbers only.',
            'fit_ish_user_id.unique' => 'That Lionheart user ID is already linked to another account.',
        ];
    }

    protected function prepareForValidation(): void
    {
        foreach (['fit_ish_user_id', 'fit_ish_serial'] as $field) {
            if (is_string($this->input($field))) {
                $this->merge([$field => trim($this->input($field))]);
            }
        }

        if ($this->input('fit_ish_user_id') === '') {
            $this->merge(['fit_ish_user_id' => null]);
        }

        if ($this->input('fit_ish_serial') === '') {
            $this->merge(['fit_ish_serial' => null]);
        }
    }
}
