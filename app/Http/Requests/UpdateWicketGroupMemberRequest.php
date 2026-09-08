<?php

namespace App\Http\Requests;

use App\Enums\WicketGroupRole;
use App\Models\WicketGroup;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateWicketGroupMemberRequest extends FormRequest
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
            'role' => ['required', Rule::enum(WicketGroupRole::class)],
        ];
    }

    public function role(): WicketGroupRole
    {
        return WicketGroupRole::from($this->validated('role'));
    }
}
