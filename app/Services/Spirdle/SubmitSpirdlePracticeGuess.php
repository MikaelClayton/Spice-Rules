<?php

namespace App\Services\Spirdle;

use App\Enums\SpirdleTile;
use App\Models\SpirdlePractice;
use App\Models\SpirdleWord;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class SubmitSpirdlePracticeGuess
{
    public function __construct(private readonly EvaluateSpirdleGuess $evaluate) {}

    /**
     * @return array{status: string, message: string|null, play: array<string, mixed>}
     */
    public function handle(User $user, string $word): array
    {
        $word = strtolower($word);

        return DB::transaction(function () use ($user, $word): array {
            $practice = SpirdlePractice::query()
                ->with('word')
                ->whereBelongsTo($user)
                ->whereNull('finished_at')
                ->lockForUpdate()
                ->first();

            if ($practice === null) {
                return [
                    'status' => 'missing',
                    'message' => 'Open practice first.',
                    'play' => [],
                ];
            }

            if ($practice->isPaused()) {
                $practice->running_since = now();
            }

            $guesses = $practice->normalizedGuesses();

            if (collect($guesses)->contains(fn (array $guess): bool => $guess['word'] === $word)) {
                return [
                    'status' => 'repeat',
                    'message' => 'You already tried that.',
                    'play' => $practice->gameState(),
                ];
            }

            if (! SpirdleWord::query()->where('word', $word)->exists()) {
                $practice->invalid_word_count++;
                $practice->save();

                return [
                    'status' => 'invalid',
                    'message' => 'Not in the word list.',
                    'play' => $practice->gameState(),
                ];
            }

            $solution = (string) $practice->roundSolution();
            $tiles = $this->evaluate->handle($word, $solution);
            $guesses[] = [
                'word' => $word,
                'tiles' => array_map(fn (SpirdleTile $tile): string => $tile->value, $tiles),
            ];

            $practice->guesses = $guesses;
            $practice->guess_count = count($guesses);

            $won = collect($tiles)->every(fn (SpirdleTile $tile): bool => $tile === SpirdleTile::Correct);

            if ($won || $practice->guess_count >= SpirdlePractice::MAX_GUESSES) {
                $practice->won = $won;
                $practice->finished_at = now();
                $practice->duration_ms = $practice->elapsedMilliseconds();
                $practice->running_since = null;
            }

            $practice->save();

            return [
                'status' => $practice->isFinished() ? 'finished' : 'ok',
                'message' => null,
                'play' => $practice->gameState(),
            ];
        });
    }
}
