<?php

namespace Tests\Feature;

use App\Models\SpirdleWord;
use App\Services\Spirdle\ImportSpirdleWords;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ImportSpirdleWordsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_upserts_answers_and_guesses_from_files(): void
    {
        $dir = storage_path('framework/testing/spirdle-words');
        File::ensureDirectoryExists($dir);
        $answers = $dir.'/answers.txt';
        $guesses = $dir.'/guesses.txt';
        File::put($answers, "crane\nabout\n");
        File::put($guesses, "crane\nabout\naahed\nnot-a-word\nab\n");
        File::put($dir.'/proper-nouns.txt', '');
        File::put($dir.'/offensive.txt', '');

        $this->artisan('spirdle:import-words', [
            '--answers' => $answers,
            '--guesses' => $guesses,
            '--proper-nouns' => $dir.'/proper-nouns.txt',
            '--offensive' => $dir.'/offensive.txt',
            '--no-public' => true,
        ])->assertSuccessful();

        $this->assertSame(3, SpirdleWord::query()->count());
        $this->assertTrue(SpirdleWord::query()->where('word', 'crane')->where('is_answer', true)->exists());
        $this->assertTrue(SpirdleWord::query()->where('word', 'aahed')->where('is_answer', false)->exists());
        $this->assertFalse(SpirdleWord::query()->where('word', 'aahed')->value('is_answer'));
    }

    public function test_proper_nouns_stay_guessable_but_are_not_answers(): void
    {
        $dir = storage_path('framework/testing/spirdle-proper-nouns');
        File::ensureDirectoryExists($dir);
        $answers = $dir.'/answers.txt';
        $guesses = $dir.'/guesses.txt';
        $proper = $dir.'/proper-nouns.txt';
        File::put($answers, "crane\njames\nabout\n");
        File::put($guesses, "crane\njames\nabout\naahed\n");
        File::put($proper, "james\n");
        File::put($dir.'/offensive.txt', '');

        $result = app(ImportSpirdleWords::class)->handle($answers, $guesses, false, $proper, $dir.'/offensive.txt');

        $this->assertSame(2, $result['answers']);
        $this->assertSame(1, $result['demoted']);
        $this->assertTrue(SpirdleWord::query()->where('word', 'crane')->where('is_answer', true)->exists());
        $this->assertTrue(SpirdleWord::query()->where('word', 'james')->exists());
        $this->assertFalse((bool) SpirdleWord::query()->where('word', 'james')->value('is_answer'));
        $this->assertFalse(SpirdleWord::query()->answers()->where('word', 'james')->exists());
    }

    public function test_offensive_words_stay_guessable_but_are_not_answers(): void
    {
        $dir = storage_path('framework/testing/spirdle-offensive');
        File::ensureDirectoryExists($dir);
        $answers = $dir.'/answers.txt';
        $guesses = $dir.'/guesses.txt';
        $proper = $dir.'/proper-nouns.txt';
        $offensive = $dir.'/offensive.txt';
        File::put($answers, "crane\nfucks\nabout\n");
        File::put($guesses, "crane\nfucks\nabout\naahed\n");
        File::put($proper, '');
        File::put($offensive, "fucks\n");

        $result = app(ImportSpirdleWords::class)->handle($answers, $guesses, false, $proper, $offensive);

        $this->assertSame(2, $result['answers']);
        $this->assertSame(1, $result['demoted']);
        $this->assertTrue(SpirdleWord::query()->where('word', 'crane')->where('is_answer', true)->exists());
        $this->assertTrue(SpirdleWord::query()->where('word', 'fucks')->exists());
        $this->assertFalse((bool) SpirdleWord::query()->where('word', 'fucks')->value('is_answer'));
    }
}
