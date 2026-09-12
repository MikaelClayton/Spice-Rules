<?php

namespace App\Services\Timezone;

use DateTimeZone;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;

class ResolveDisplayTimezone
{
    public const COOKIE = 'display_timezone';

    public const CONTEXT_KEY = 'display_timezone';

    public function fromRequest(Request $request): string
    {
        return $this->validName($request->cookies->get(self::COOKIE))
            ?? (string) config('app.timezone');
    }

    public function name(): string
    {
        $value = Context::get(self::CONTEXT_KEY);

        return $this->validName($value) ?? (string) config('app.timezone');
    }

    public function validName(mixed $candidate): ?string
    {
        if (! is_string($candidate) || $candidate === '') {
            return null;
        }

        return in_array($candidate, DateTimeZone::listIdentifiers(), true) ? $candidate : null;
    }
}
