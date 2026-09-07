<?php

namespace App\Http\Requests;

use App\Enums\WicketFineType;
use App\Models\WicketGroup;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWicketFineRequest extends FormRequest
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
        $group = $this->route('wicketGroup');
        $groupId = $group instanceof WicketGroup ? $group->id : 0;

        return [
            'issued_to_user_ids' => ['required', 'array', 'min:1'],
            'issued_to_user_ids.*' => [
                'integer',
                'distinct',
                Rule::exists('user_wicket_group', 'user_id')->where('wicket_group_id', $groupId),
            ],
            'type' => ['required', Rule::enum(WicketFineType::class)],
            'sips' => [
                'exclude_unless:type,'.WicketFineType::Sips->value,
                'required',
                'integer',
                'min:1',
                'max:'.WicketFineType::MAX_SIP_FINE,
            ],
            'reason' => ['required', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'issued_to_user_ids.required' => 'Pick someone to fine.',
            'issued_to_user_ids.min' => 'Pick someone to fine.',
            'issued_to_user_ids.*.exists' => 'That person is not in this group.',
            'type.required' => 'Pick a fine.',
            'sips.required' => 'How many sips?',
            'sips.max' => '8 sips is a down down. Pick 1 to '.WicketFineType::MAX_SIP_FINE.'.',
            'reason.required' => 'Give a reason for the fine.',
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->reason)) {
            $this->merge(['reason' => trim($this->reason)]);
        }

        if ($this->filled('issued_to_user_id') && ! $this->has('issued_to_user_ids')) {
            $this->merge([
                'issued_to_user_ids' => [$this->input('issued_to_user_id')],
            ]);
        }

        $punishment = $this->input('punishment');

        if (is_string($punishment) && $punishment !== '') {
            if (preg_match('/^sips_([1-7])$/', $punishment, $matches) === 1) {
                $this->merge([
                    'type' => WicketFineType::Sips->value,
                    'sips' => (int) $matches[1],
                ]);

                return;
            }

            $this->merge(['type' => $punishment]);

            return;
        }

        if ($this->filled('type')) {
            return;
        }

        $sips = (int) $this->input('sips');

        if ($sips >= 1 && $sips <= WicketFineType::MAX_SIP_FINE) {
            $this->merge(['type' => WicketFineType::Sips->value]);
        }
    }

    public function type(): WicketFineType
    {
        return $this->enum('type', WicketFineType::class);
    }

    /**
     * @return list<int>
     */
    public function issuedToUserIds(): array
    {
        return array_values(array_unique(array_map(
            intval(...),
            $this->validated('issued_to_user_ids'),
        )));
    }
}
