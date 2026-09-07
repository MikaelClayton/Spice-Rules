<?php

namespace App\Http\Requests;

use App\Models\WicketGroup;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreWicketGroupMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        $group = $this->route('wicketGroup');

        return $group instanceof WicketGroup
            && $this->user() !== null
            && $group->hasMember($this->user());
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'user_ids' => ['required', 'array', 'min:1'],
            'user_ids.*' => ['integer', 'distinct', 'exists:users,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'user_ids.required' => 'Pick at least one person to add.',
            'user_ids.min' => 'Pick at least one person to add.',
            'user_ids.*.exists' => 'That person is not on Spice Rules.',
        ];
    }

    /**
     * @return list<int>
     */
    public function userIds(): array
    {
        return array_values(array_unique(array_map(
            intval(...),
            $this->validated('user_ids'),
        )));
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $group = $this->route('wicketGroup');

                if (! $group instanceof WicketGroup) {
                    return;
                }

                $alreadyInGroup = $group->users()
                    ->whereIn(
                        'users.id',
                        collect($this->input('user_ids', []))->map(fn ($id): int => (int) $id)->all(),
                    )
                    ->exists();

                if ($alreadyInGroup) {
                    $validator->errors()->add('user_ids', 'That person is already in this group.');
                }
            },
        ];
    }
}
