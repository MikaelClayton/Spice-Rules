<?php

namespace App\Http\Controllers;

use App\Models\Geoguesser;
use App\Models\GeoguesserChallenge;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class GeoguessrController extends Controller
{
    public function index(): View
    {
        $results = GeoguesserChallenge::query()
            ->with('geoguesser.user')
            ->whereDate('attempted_at', today())
            ->orderByDesc('total_score')
            ->orderBy('updated_at')
            ->get();

        $history = GeoguesserChallenge::query()
            ->with('geoguesser.user')
            ->whereNotNull('total_score')
            ->orderBy('attempted_at')
            ->get();

        return view('geoguessr.index', [
            'results' => $results,
            'date' => today(),
            'activeTab' => request()->string('tab')->toString() === 'graphs' ? 'graphs' : 'today',
            'board' => $this->boardPayload($history),
        ]);
    }

    /**
     * @param  Collection<int, GeoguesserChallenge>  $challenges
     * @return array{currentUserId: int|null, players: list<array<string, mixed>>, challenges: list<array<string, mixed>>}
     */
    private function boardPayload($challenges): array
    {
        $players = $challenges
            ->pluck('geoguesser')
            ->filter()
            ->unique('id')
            ->values();

        return [
            'currentUserId' => Auth::id(),
            'players' => $players->map(fn (Geoguesser $player) => [
                'id' => $player->id,
                'name' => $player->user?->name ?? $player->username,
                'nick' => $player->username,
                'isYou' => $player->user_id === Auth::id(),
                'streak' => $player->daily_challenge_current_streak,
                'bestStreak' => $player->daily_challenge_streak,
            ])->values()->all(),
            'challenges' => $challenges->map(fn (GeoguesserChallenge $challenge) => [
                'playerId' => $challenge->geoguesser_id,
                'date' => $challenge->attempted_at?->toDateString(),
                'score' => $challenge->total_score,
                'distance' => $challenge->total_distance,
                'steps' => $challenge->total_steps_count,
            ])->values()->all(),
        ];
    }
}
