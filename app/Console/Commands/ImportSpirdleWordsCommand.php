<?php

namespace App\Console\Commands;

use App\Services\Cron\RecordCronRun;
use App\Services\Spirdle\ImportSpirdleWords;
use Illuminate\Console\Command;

class ImportSpirdleWordsCommand extends Command
{
    protected $signature = 'spirdle:import-words
                            {--answers= : Path to the answer list}
                            {--guesses= : Path to the guess list}
                            {--proper-nouns= : Path to proper nouns that stay guessable but never answers}
                            {--offensive= : Path to offensive words that stay guessable but never answers}
                            {--no-public : Skip writing public/spirdle-guesses.txt}';

    protected $description = 'Load Spirdle answer and guess lists into the word table';

    public function handle(ImportSpirdleWords $import, RecordCronRun $recordCronRun): int
    {
        $result = $recordCronRun->handle(
            'spirdle:import-words',
            fn (): array => $import->handle(
                $this->option('answers') ?: null,
                $this->option('guesses') ?: null,
                ! $this->option('no-public'),
                $this->option('proper-nouns') ?: null,
                $this->option('offensive') ?: null,
            ),
        );

        $this->info("Imported {$result['guesses']} guessable words ({$result['answers']} answers, {$result['demoted']} names/offensive kept as guesses only).");

        return self::SUCCESS;
    }
}
