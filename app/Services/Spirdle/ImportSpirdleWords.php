<?php

namespace App\Services\Spirdle;

use App\Models\SpirdlePlay;
use App\Models\SpirdleWord;
use Illuminate\Support\Facades\File;

class ImportSpirdleWords
{
    public const CHUNK_SIZE = 500;

    /**
     * @return array{synced: int, answers: int, guesses: int}
     */
    public function handle(?string $answersPath = null, ?string $guessesPath = null, bool $writePublicList = true): array
    {
        $answers = $this->wordsFrom($answersPath ?? database_path('data/spirdle/answers.txt'));
        $guesses = $this->wordsFrom($guessesPath ?? database_path('data/spirdle/guesses.txt'));

        foreach ($answers as $word) {
            $guesses[$word] = $word;
        }

        $rows = [];

        foreach ($guesses as $word) {
            $rows[] = [
                'word' => $word,
                'is_answer' => isset($answers[$word]),
            ];
        }

        foreach (array_chunk($rows, self::CHUNK_SIZE) as $chunk) {
            SpirdleWord::query()->upsert($chunk, ['word'], ['is_answer']);
        }

        if ($writePublicList) {
            File::put(
                public_path('spirdle-guesses.txt'),
                implode("\n", array_keys($guesses))."\n",
            );
        }

        return [
            'synced' => count($rows),
            'answers' => count($answers),
            'guesses' => count($guesses),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function wordsFrom(string $path): array
    {
        if (! is_file($path)) {
            return [];
        }

        $words = [];

        foreach (file($path, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
            $word = strtolower(trim($line));

            if (strlen($word) !== SpirdlePlay::WORD_LENGTH || preg_match('/^[a-z]+$/', $word) !== 1) {
                continue;
            }

            $words[$word] = $word;
        }

        return $words;
    }
}
