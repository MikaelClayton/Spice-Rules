<?php

namespace App\Http\Requests;

use App\Models\FitIshStudio;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class DestroyFitIshStudioRequest extends FormRequest
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
        return [];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $studio = $this->route('fitIshStudio');

                if ($studio instanceof FitIshStudio && $studio->sessions()->exists()) {
                    $validator->errors()->add('studio', 'This studio has synced classes, so it cannot be deleted.');
                }
            },
        ];
    }
}
