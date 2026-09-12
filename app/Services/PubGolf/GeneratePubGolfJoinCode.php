<?php

namespace App\Services\PubGolf;

use App\Models\PubGolfCrawl;
use RuntimeException;

class GeneratePubGolfJoinCode
{
    public const LENGTH = 6;

    public const ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    public function handle(): string
    {
        $alphabetLength = strlen(self::ALPHABET) - 1;

        for ($attempt = 0; $attempt < 25; $attempt++) {
            $code = '';

            for ($i = 0; $i < self::LENGTH; $i++) {
                $code .= self::ALPHABET[random_int(0, $alphabetLength)];
            }

            if (! PubGolfCrawl::query()->where('join_code', $code)->exists()) {
                return $code;
            }
        }

        throw new RuntimeException('Could not generate a Pub Golf join code.');
    }
}
