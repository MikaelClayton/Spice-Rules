<?php

namespace Tests\Feature;

use App\Models\SpirdlePuzzle;
use App\Models\SpirdleWord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OpenSpirdleDailyCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_opens_today_with_an_unused_answer(): void
    {
        $this->travelTo('2026-09-16 00:00:00');

        $used = SpirdleWord::factory()->create(['word' => 'about', 'is_answer' => true]);
        $fresh = SpirdleWord::factory()->create(['word' => 'crane', 'is_answer' => true]);
        SpirdleWord::factory()->guessOnly()->create(['word' => 'aahed']);

        SpirdlePuzzle::factory()->create([
            'spirdle_word_id' => $used->id,
            'play_date' => '2026-09-15',
        ]);

        $this->artisan('spirdle:open-daily')->assertSuccessful();

        $today = SpirdlePuzzle::query()->whereDate('play_date', '2026-09-16')->first();

        $this->assertNotNull($today);
        $this->assertSame($fresh->id, $today->spirdle_word_id);

        $this->artisan('spirdle:open-daily')->assertSuccessful();

        $this->assertSame(1, SpirdlePuzzle::query()->whereDate('play_date', '2026-09-16')->count());
    }

    public function test_it_fails_when_no_answers_are_loaded(): void
    {
        $this->artisan('spirdle:open-daily')->assertFailed();
    }
}
