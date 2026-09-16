<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFitIshStudioRequest extends FormRequest
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
            'studio_id' => ['required', 'integer', 'min:1', Rule::unique('fit_ish_studios', 'external_id')],
            'name' => ['required', 'string', 'max:120'],
            'code' => ['required', 'string', 'max:32', Rule::unique('fit_ish_studios', 'code')],
            'timezone' => ['required', 'timezone:all'],
            'is_loaner' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'studio_id.required' => 'Enter the studio ID from Lionheart.',
            'studio_id.unique' => 'That studio ID is already saved.',
            'code.unique' => 'That studio code is already saved.',
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->name)) {
            $this->merge(['name' => trim($this->name)]);
        }

        if (is_string($this->code)) {
            $this->merge(['code' => strtolower(trim($this->code))]);
        }
    }
}
