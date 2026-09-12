<?php

namespace App\Http\Requests;

use App\Models\WicketGroup;
use Illuminate\Foundation\Http\FormRequest;

class UpdateWicketGroupRequest extends FormRequest
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
        return [
            'is_tournament' => ['required', 'boolean'],
            'notify_all_on_fine' => ['sometimes', 'boolean'],
        ];
    }
}
