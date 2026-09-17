<?php

namespace App\Services\Push;

use App\Models\DeviceToken;
use App\Models\SpirdlePlay;
use App\Services\Spirdle\RankSpirdlePlays;
use Illuminate\Support\Str;

class NotifySpirdleFinish
{
    public function __construct(
        private readonly FcmClient $fcm,
        private readonly RankSpirdlePlays $ranker,
    ) {}

    public function afterResponse(int $playId): void
    {
        defer(static function (): void {
            if (function_exists('fastcgi_finish_request')) {
                fastcgi_finish_request();
            }
        }, 'push-flush-response');

        defer(function () use ($playId): void {
            $this->handle($playId);
        }, 'spirdle-notify-finish-'.$playId);
    }

    public function handle(int $playId): void
    {
        $play = SpirdlePlay::query()
            ->with('user')
            ->finished()
            ->find($playId);

        if ($play === null || $play->user_id === null) {
            return;
        }

        $playerIds = SpirdlePlay::query()
            ->finished()
            ->where('user_id', '!=', $play->user_id)
            ->distinct()
            ->pluck('user_id');

        if ($playerIds->isEmpty()) {
            return;
        }

        $tokens = DeviceToken::query()
            ->whereIn('user_id', $playerIds)
            ->orderBy('id')
            ->pluck('token');

        if ($tokens->isEmpty()) {
            return;
        }

        $this->fcm->sendToTokens($tokens, [
            'title' => 'Spirdle',
            'body' => $this->body($play),
            'url' => route('spirdle.index'),
        ]);
    }

    private function body(SpirdlePlay $play): string
    {
        $name = $play->user?->name ?? 'Someone';
        $place = $this->ranker->placeFor($play);
        $standing = $place === null ? '' : ' and is currently in '.$this->ranker->ordinal($place);

        if (! $play->won) {
            return $name.' just missed today\'s Spirdle'.$standing.'.';
        }

        $guesses = $play->guess_count;

        return $name.' just solved today\'s Spirdle in '.$guesses.' '.Str::plural('guess', $guesses).$standing.'.';
    }
}
