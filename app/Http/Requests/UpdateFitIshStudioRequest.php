<?php

namespace App\Http\Requests;

use App\Models\FitIshStudio;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateFitIshStudioRequest extends FormRequest
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
        $studio = $this->route('fitIshStudio');
        $studioId = $studio instanceof FitIshStudio ? $studio->id : null;

        return [
            'studio_id' => ['required', 'integer', 'min:1', Rule::unique('fit_ish_studios', 'external_id')->ignore($studioId)],
            'name' => ['required', 'string', 'max:120'],
            'code' => ['required', 'string', 'max:32', Rule::unique('fit_ish_studios', 'code')->ignore($studioId)],
            'timezone' => ['required', 'timezone:all'],
            'is_loaner' => ['sometimes', 'boolean'],
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
