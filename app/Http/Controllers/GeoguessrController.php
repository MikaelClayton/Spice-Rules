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
            'progress' => $this->viewerProgress(Auth::user()?->geoguesser),
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
                'xp' => $this->arrayValue($challenge->progress, 'xp'),
            ])->values()->all(),
        ];
    }

    /**
     * @return array{level: mixed, xp: int|null, nextLevel: mixed, nextLevelXp: int|null, percent: int|null}
     */
    private function viewerProgress(?Geoguesser $geoguesser): array
    {
        $progress = $geoguesser?->challenges()
            ->whereNotNull('progress')
            ->orderByDesc('attempted_at')
            ->first()
            ?->progress ?? [];
        $xp = $this->arrayValue($progress, 'xp');
        $levelXp = $this->arrayValue($progress, 'levelXp');
        $nextLevelXp = $this->arrayValue($progress, 'nextLevelXp');
        $span = $nextLevelXp !== null && $levelXp !== null ? max($nextLevelXp - $levelXp, 1) : null;
        $earned = $xp !== null && $levelXp !== null ? max($xp - $levelXp, 0) : null;

        return [
            'level' => $progress['level'] ?? null,
            'xp' => $xp,
            'nextLevel' => $progress['nextLevel'] ?? null,
            'nextLevelXp' => $nextLevelXp,
            'percent' => $span !== null && $earned !== null ? min(100, (int) round(100 * $earned / $span)) : null,
        ];
    }

    /**
     * @param  array<string, mixed>|null  $values
     */
    private function arrayValue(?array $values, string $key): ?int
    {
        $value = $values[$key] ?? null;

        return is_numeric($value) ? (int) $value : null;
    }
}
