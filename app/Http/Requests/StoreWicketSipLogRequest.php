<?php

namespace App\Http\Requests;

use App\Models\WicketFine;
use App\Models\WicketGroup;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreWicketSipLogRequest extends FormRequest
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
            'sips' => ['required', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'sips.required' => 'How many sips did you drink?',
            'sips.min' => 'Drink at least 1 sip.',
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $group = $this->route('wicketGroup');
                $user = $this->user();

                if (! $group instanceof WicketGroup || $user === null) {
                    return;
                }

                $outstanding = WicketFine::query()
                    ->whereBelongsTo($group)
                    ->where('issued_to_user_id', $user->id)
                    ->whereNull('completed_at')
                    ->where('sips_owed', '>', 0)
                    ->get()
                    ->sum(fn (WicketFine $fine): int => $fine->remainingSips());

                if ($outstanding === 0) {
                    $validator->errors()->add('sips', 'You have no sip fines left to drink.');

                    return;
                }

                if ($this->integer('sips') > $outstanding) {
                    $validator->errors()->add(
                        'sips',
                        $outstanding === 1
                            ? 'You only have 1 sip left.'
                            : 'You only have '.$outstanding.' sips left.',
                    );
                }
            },
        ];
    }
}
