<?php

namespace App\Services\Geoguessr;

use Illuminate\Support\Facades\Http;

class GeoguessrClient
{
    public function __construct(private readonly string $baseUrl = 'https://www.geoguessr.com') {}

    /**
     * @return array<string, mixed>
     */
    public function profile(string $ncfa): array
    {
        return $this->get('/api/v3/profiles', $ncfa);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function weeklyDailyChallenges(string $ncfa): array
    {
        return $this->get('/api/v3/challenges/daily-challenges/me/week', $ncfa);
    }

    /**
     * @return array<string, mixed>
     */
    public function stats(string $ncfa): array
    {
        return $this->get('/api/v3/profiles/stats', $ncfa);
    }

    public static function normalizeNcfa(string $ncfa): string
    {
        $ncfa = trim($ncfa);
        $ncfa = trim($ncfa, "\"'");

        if (str_starts_with($ncfa, '_ncfa=')) {
            $ncfa = substr($ncfa, strlen('_ncfa='));
        }

        return $ncfa;
    }

    /**
     * @return array<string, mixed>
     */
    private function get(string $path, string $ncfa): array
    {
        $response = Http::acceptJson()
            ->timeout(20)
            ->withHeaders([
                'Cookie' => '_ncfa='.self::normalizeNcfa($ncfa),
            ])
            ->withOptions([
                'proxy' => '',
            ])
            ->get($this->baseUrl.$path)
            ->throw();

        /** @var array<string, mixed> $json */
        $json = $response->json();

        return $json;
    }
}
