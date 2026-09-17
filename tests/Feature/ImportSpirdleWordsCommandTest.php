<?php

namespace Tests\Feature;

use App\Models\SpirdleWord;
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

        $this->artisan('spirdle:import-words', [
            '--answers' => $answers,
            '--guesses' => $guesses,
            '--no-public' => true,
        ])->assertSuccessful();

        $this->assertSame(3, SpirdleWord::query()->count());
        $this->assertTrue(SpirdleWord::query()->where('word', 'crane')->where('is_answer', true)->exists());
        $this->assertTrue(SpirdleWord::query()->where('word', 'aahed')->where('is_answer', false)->exists());
        $this->assertFalse(SpirdleWord::query()->where('word', 'aahed')->value('is_answer'));
    }
}
