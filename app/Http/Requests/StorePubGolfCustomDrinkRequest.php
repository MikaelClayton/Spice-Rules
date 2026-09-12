<?php

namespace App\Http\Requests;

use App\Enums\PubGolfDrinkCategory;
use App\Models\PubGolfCrawl;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use RuntimeException;

class StorePubGolfCustomDrinkRequest extends FormRequest
{
    public function authorize(): bool
    {
        $crawl = $this->route('pubGolfCrawl');

        return $crawl instanceof PubGolfCrawl
            && $this->user() !== null
            && $crawl->isActiveParticipant($this->user());
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:80'],
            'category' => ['required', Rule::enum(PubGolfDrinkCategory::class)],
            'photo' => [
                'required',
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
            'name.required' => 'Give the drink a name.',
            'category.required' => 'Pick a category.',
            'category.enum' => 'Pick a category.',
            'photo.required' => 'Add a photo of the drink.',
            'photo.file' => 'Add a photo of the drink.',
            'photo.max' => 'Keep the photo under 10 MB.',
            'photo.mimes' => 'Use a JPEG, PNG, or WebP photo. HEIC is not allowed.',
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $photo = $this->file('photo');

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

    public function category(): PubGolfDrinkCategory
    {
        return PubGolfDrinkCategory::from($this->validated('category'));
    }

    public function photo(): UploadedFile
    {
        $photo = $this->file('photo');

        if (! $photo instanceof UploadedFile) {
            throw new RuntimeException('Validated photo was missing.');
        }

        return $photo;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->name)) {
            $this->merge(['name' => trim($this->name)]);
        }
    }
}
