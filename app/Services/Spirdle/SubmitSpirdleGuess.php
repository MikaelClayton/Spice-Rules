<?php

namespace App\Services\Spirdle;

use App\Enums\SpirdleTile;
use App\Models\SpirdlePlay;
use App\Models\SpirdleWord;
use App\Models\User;
use App\Services\Push\NotifySpirdleFinish;
use Illuminate\Support\Facades\DB;

class SubmitSpirdleGuess
{
    public function __construct(
        private readonly EvaluateSpirdleGuess $evaluate,
        private readonly NotifySpirdleFinish $notifier,
    ) {}

    /**
     * @return array{status: string, message: string|null, play: array<string, mixed>}
     */
    public function handle(User $user, string $word): array
    {
        $word = strtolower($word);

        $result = DB::transaction(function () use ($user, $word): array {
            $play = SpirdlePlay::query()
                ->with('puzzle.word')
                ->whereBelongsTo($user)
                ->whereHas('puzzle', fn ($query) => $query->whereDate('play_date', today()))
                ->lockForUpdate()
                ->first();

            if ($play === null) {
                return [
                    'status' => 'missing',
                    'message' => 'Open today\'s board first.',
                    'play' => [],
                ];
            }

            if ($play->isFinished()) {
                return [
                    'status' => 'finished',
                    'message' => 'You already finished today\'s Spirdle.',
                    'play' => $play->gameState(),
                ];
            }

            if ($play->isPaused()) {
                $play->running_since = now();
            }

            $guesses = $play->normalizedGuesses();

            if (collect($guesses)->contains(fn (array $guess): bool => $guess['word'] === $word)) {
                return [
                    'status' => 'repeat',
                    'message' => 'You already tried that.',
                    'play' => $play->gameState(),
                ];
            }

            if (! SpirdleWord::query()->where('word', $word)->exists()) {
                $play->invalid_word_count++;
                $play->save();

                return [
                    'status' => 'invalid',
                    'message' => 'Not in the word list.',
                    'play' => $play->gameState(),
                ];
            }

            $tiles = $this->evaluate->handle($word, $play->puzzle->solution());
            $guesses[] = [
                'word' => $word,
                'tiles' => array_map(fn (SpirdleTile $tile): string => $tile->value, $tiles),
            ];

            $play->guesses = $guesses;
            $play->guess_count = count($guesses);

            $won = collect($tiles)->every(fn (SpirdleTile $tile): bool => $tile === SpirdleTile::Correct);
            $justFinished = false;

            if ($won || $play->guess_count >= SpirdlePlay::MAX_GUESSES) {
                $play->won = $won;
                $play->finished_at = now();
                $play->duration_ms = $play->elapsedMilliseconds();
                $play->running_since = null;
                $justFinished = true;
            }

            $play->save();

            return [
                'status' => $play->isFinished() ? 'finished' : 'ok',
                'message' => null,
                'play' => $play->gameState(),
                'notifyPlayId' => $justFinished ? $play->id : null,
            ];
        });

        if (is_int($result['notifyPlayId'] ?? null)) {
            $this->notifier->afterResponse($result['notifyPlayId']);
        }

        unset($result['notifyPlayId']);

        return $result;
    }
}
