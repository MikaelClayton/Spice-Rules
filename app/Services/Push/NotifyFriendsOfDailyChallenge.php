<?php

namespace App\Services\Push;

use App\Models\DeviceToken;
use App\Models\Geoguesser;
use App\Models\GeoguesserChallenge;
use App\Services\Geoguessr\RankTodaysChallenges;

class NotifyFriendsOfDailyChallenge
{
    public function __construct(
        private readonly FcmClient $fcm,
        private readonly RankTodaysChallenges $ranker,
    ) {}

    public function handle(Geoguesser $geoguesser, GeoguesserChallenge $challenge): void
    {
        if ($geoguesser->user_id === null) {
            return;
        }

        $tokens = DeviceToken::query()
            ->where('user_id', '!=', $geoguesser->user_id)
            ->orderBy('id')
            ->pluck('token');

        if ($tokens->isEmpty()) {
            return;
        }

        $geoguesser->loadMissing('user');
        $challenge->refresh();

        $this->fcm->sendToTokens($tokens, [
            'title' => 'Daily GeoGuessr',
            'body' => $this->body($geoguesser, $challenge),
            'url' => route('geoguessr.index'),
        ]);
    }

    private function body(Geoguesser $geoguesser, GeoguesserChallenge $challenge): string
    {
        $name = $geoguesser->displayName();
        $score = $challenge->total_score;
        $place = $this->ranker->placeFor($challenge);
        $standing = $place === null ? '' : ' and is currently in '.$this->ranker->ordinal($place);

        if ($score === null) {
            return $name.' just finished today\'s GeoGuessr'.$standing.'.';
        }

        return $name.' just scored '.number_format($score).$standing.'.';
    }
}
