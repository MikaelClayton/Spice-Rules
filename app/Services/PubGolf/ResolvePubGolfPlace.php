<?php

namespace App\Services\PubGolf;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class ResolvePubGolfPlace
{
    public function handle(?float $latitude, ?float $longitude): ?string
    {
        if ($latitude === null || $longitude === null) {
            return null;
        }

        try {
            $response = Http::acceptJson()
                ->withUserAgent((string) config('pub-golf.location_search_user_agent'))
                ->connectTimeout((int) config('pub-golf.location_search_connect_timeout'))
                ->timeout((int) config('pub-golf.location_search_timeout'))
                ->get((string) config('pub-golf.location_reverse_url'), [
                    'lat' => sprintf('%.7f', $latitude),
                    'lon' => sprintf('%.7f', $longitude),
                ]);
        } catch (ConnectionException) {
            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        $properties = $response->json('features.0.properties');

        if (! is_array($properties)) {
            return null;
        }

        return $this->label($properties);
    }

    /**
     * @param  array<array-key, mixed>  $properties
     */
    private function label(array $properties): ?string
    {
        $name = trim((string) ($properties['name'] ?? ''));

        if ($name === '') {
            return null;
        }

        $locality = trim((string) (
            $properties['city']
            ?? $properties['town']
            ?? $properties['village']
            ?? $properties['municipality']
            ?? $properties['district']
            ?? $properties['county']
            ?? ''
        ));

        $label = $locality !== '' && mb_strtolower($locality) !== mb_strtolower($name)
            ? $name.', '.$locality
            : $name;

        if (Str::length($label) > 80) {
            return Str::substr($label, 0, 80);
        }

        return $label;
    }
}
