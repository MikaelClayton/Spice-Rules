<?php

namespace App\Http\Requests;

use App\Models\User;
use App\Models\WicketGroup;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class DestroyWicketGroupMemberRequest extends FormRequest
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
        return [];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $group = $this->route('wicketGroup');
                $member = $this->route('user');

                if ($group instanceof WicketGroup && $member instanceof User && $group->isOwnedBy($member)) {
                    $validator->errors()->add('user', 'The group owner cannot be removed.');
                }
            },
        ];
    }
}
