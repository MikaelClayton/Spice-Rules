<?php

namespace App\Http\Requests;

use App\Models\WicketGroup;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DestroyWicketGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        $group = $this->route('wicketGroup');

        return $group instanceof WicketGroup
            && $this->user() !== null
            && $group->isOwnedBy($this->user());
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $group = $this->route('wicketGroup');
        $name = $group instanceof WicketGroup ? $group->name : '';

        return [
            'name' => ['required', 'string', Rule::in([$name])],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Type the group name to confirm.',
            'name.in' => 'Type the group name exactly to confirm.',
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->name)) {
            $this->merge(['name' => trim($this->name)]);
        }
    }
}
