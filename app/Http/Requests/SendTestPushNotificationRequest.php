<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SendTestPushNotificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'audience' => ['required', 'in:me,everyone'],
            'title' => ['required', 'string', 'max:80'],
            'body' => ['required', 'string', 'max:180'],
        ];
    }
}
