<?php

namespace App\Http\Requests;

use App\Models\PubGolfCrawl;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Validator;

class StorePubGolfChatMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        $crawl = $this->route('pubGolfCrawl');

        return $crawl instanceof PubGolfCrawl
            && $this->user() !== null
            && $crawl->hasParticipant($this->user());
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'body' => ['nullable', 'string', 'max:1000'],
            'photo' => [
                'nullable',
                'file',
                'max:'.config('pub-golf.photo_max_kilobytes'),
                'mimes:jpg,jpeg,png,webp',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'body.max' => 'Keep the message under 1000 characters.',
            'photo.file' => 'Add a JPEG, PNG, or WebP photo.',
            'photo.max' => 'Keep the photo under 10 MB.',
            'photo.mimes' => 'Use a JPEG, PNG, or WebP photo. HEIC is not allowed.',
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $crawl = $this->route('pubGolfCrawl');

                if ($crawl instanceof PubGolfCrawl && ! $crawl->isOpen()) {
                    $validator->errors()->add('body', 'That crawl has already wrapped up.');

                    return;
                }

                $body = trim((string) $this->input('body'));
                $photo = $this->file('photo');

                if ($body === '' && ! $photo instanceof UploadedFile) {
                    $validator->errors()->add('body', 'Write a message or add a photo.');
                }

                if (! $photo instanceof UploadedFile) {
                    return;
                }

                $name = mb_strtolower($photo->getClientOriginalName());
                $mime = mb_strtolower((string) $photo->getMimeType());
                $clientMime = mb_strtolower($photo->getClientMimeType());

                if (
                    str_ends_with($name, '.heic')
                    || str_ends_with($name, '.heif')
                    || str_contains($mime, 'heic')
                    || str_contains($mime, 'heif')
                    || str_contains($clientMime, 'heic')
                    || str_contains($clientMime, 'heif')
                ) {
                    $validator->errors()->add('photo', 'Use a JPEG, PNG, or WebP photo. HEIC is not allowed.');
                }
            },
        ];
    }

    public function messageBody(): ?string
    {
        $body = trim((string) $this->validated('body', ''));

        return $body === '' ? null : $body;
    }

    public function photo(): ?UploadedFile
    {
        $photo = $this->file('photo');

        return $photo instanceof UploadedFile ? $photo : null;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->body)) {
            $this->merge(['body' => trim($this->body)]);
        }
    }
}
