<?php

namespace App\Http\Requests;

use App\Services\Timezone\ResolveDisplayTimezone;
use Illuminate\Foundation\Http\FormRequest;

class StorePubGolfCrawlRequest extends FormRequest
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
        return [
            'name' => ['nullable', 'string', 'max:80'],
        ];
    }

    public function crawlName(): string
    {
        $name = $this->validated('name');

        if (is_string($name) && $name !== '') {
            return $name;
        }

        return 'Pub Golf · '.now()->timezone($this->container->make(ResolveDisplayTimezone::class)->name())->format('j M');
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->name)) {
            $this->merge(['name' => trim($this->name)]);
        }
    }
}
