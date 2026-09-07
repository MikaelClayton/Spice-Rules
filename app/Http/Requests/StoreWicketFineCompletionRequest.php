<?php

namespace App\Http\Requests;

use App\Models\WicketFine;
use App\Models\WicketGroup;
use Illuminate\Foundation\Http\FormRequest;

class StoreWicketFineCompletionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $group = $this->route('wicketGroup');
        $fine = $this->route('fine');
        $user = $this->user();

        return $group instanceof WicketGroup
            && $fine instanceof WicketFine
            && $user !== null
            && $group->hasMember($user)
            && $fine->issued_to_user_id === $user->id
            && $fine->isOutstanding()
            && $fine->type->isSip() === false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
