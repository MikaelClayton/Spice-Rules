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
            'activeTab' => $this->activeTab(),
            'board' => $this->boardPayload($history),
            'dailies' => $this->dailiesPayload(),
            'progress' => $this->viewerProgress(Auth::user()?->geoguesser),
            ...$this->todayAwards($results),
        ]);
    }

    /**
     * @param  Collection<int, GeoguesserChallenge>  $results
     * @return array{closestDistance: int|null, furthestDistance: int|null, fewestSteps: int|null, mostSteps: int|null}
     */
    private function todayAwards(Collection $results): array
    {
        [$closest, $furthest] = $this->minMax($results, 'total_distance');
        [$fewest, $most] = $this->minMax($results, 'total_steps_count');

        return [
            'closestDistance' => $closest,
            'furthestDistance' => $furthest,
            'fewestSteps' => $fewest,
            'mostSteps' => $most,
        ];
    }

    /**
     * @param  Collection<int, GeoguesserChallenge>  $results
     * @return array{0: int|null, 1: int|null}
     */
    private function minMax(Collection $results, string $column): array
    {
        $values = $results
            ->pluck($column)
            ->filter(fn ($value): bool => $value !== null)
            ->map(fn ($value): int => (int) $value);

        $min = $values->min();
        $max = $values->max();

        if ($values->count() < 2 || $min === $max) {
            return [null, null];
        }

        return [$min, $max];
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
                'label' => $this->playerLabel($player),
                'color' => $player->boardColor(),
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

    private function activeTab(): string
    {
        $tab = request()->string('tab')->toString();

        return in_array($tab, ['graphs', 'challenges'], true) ? $tab : 'today';
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function dailiesPayload(): array
    {
        $viewerId = Auth::id();
        $grouped = GeoguesserChallenge::query()
            ->with(['geoguesser.user', 'rounds'])
            ->whereHas('rounds')
            ->whereNotNull('challenge_token')
            ->orderByDesc('attempted_at')
            ->get()
            ->groupBy('challenge_token');

        $dailies = $grouped
            ->map(function (Collection $group) use ($viewerId): array {
                $first = $group->first();
                $date = $first?->attempted_at?->toDateString();
                $isToday = $date === today()->toDateString();
                $viewerPlayed = $group->contains(
                    fn (GeoguesserChallenge $challenge): bool => $challenge->geoguesser?->user_id === $viewerId
                        && $challenge->rounds->isNotEmpty(),
                );
                $locked = $isToday && $viewerId !== null && ! $viewerPlayed;
                $rounds = [];

                foreach ($group as $challenge) {
                    $player = $challenge->geoguesser;

                    foreach ($challenge->rounds as $round) {
                        $number = (int) $round->round_number;
                        $rounds[$number] ??= [
                            'number' => $number,
                            'actualLat' => $round->actual_lat,
                            'actualLng' => $round->actual_lng,
                            'country' => $round->country_code,
                            'panoId' => $round->pano_id,
                            'heading' => $round->heading,
                            'pitch' => $round->pitch,
                            'guesses' => [],
                        ];

                        if (blank($rounds[$number]['panoId']) && filled($round->pano_id)) {
                            $rounds[$number]['panoId'] = $round->pano_id;
                            $rounds[$number]['heading'] = $round->heading;
                            $rounds[$number]['pitch'] = $round->pitch;
                        }

                        if ($locked || $round->guess_lat === null || $round->guess_lng === null || $player === null) {
                            continue;
                        }

                        $rounds[$number]['guesses'][] = [
                            'playerId' => $player->id,
                            'label' => $this->playerLabel($player),
                            'initials' => $this->playerInitials($player),
                            'color' => $player->boardColor(),
                            'lat' => $round->guess_lat,
                            'lng' => $round->guess_lng,
                            'score' => $round->score,
                            'percent' => $round->percentage,
                            'time' => $round->time,
                            'steps' => $round->steps_count,
                            'distance' => $round->distance_in_meters,
                        ];
                    }
                }

                ksort($rounds);

                return [
                    'token' => $first?->challenge_token,
                    'date' => $date,
                    'label' => $first?->attempted_at?->toFormattedDateString() ?? $first?->challenge_token,
                    'mapName' => $group->pluck('map_name')->filter()->first() ?? 'World',
                    'playerCount' => $group->count(),
                    'locked' => $locked,
                    'rounds' => $locked ? [] : array_values($rounds),
                ];
            })
            ->sortByDesc('date')
            ->values();

        if (! $dailies->contains(fn (array $daily): bool => $daily['date'] === today()->toDateString())) {
            $dailies->prepend([
                'token' => 'today',
                'date' => today()->toDateString(),
                'label' => today()->toFormattedDateString(),
                'mapName' => 'World',
                'playerCount' => 0,
                'locked' => true,
                'rounds' => [],
            ]);
        }

        return $dailies->values()->all();
    }

    private function playerLabel(Geoguesser $player): string
    {
        $name = $player->user?->name ?? $player->username;
        $nick = $player->username;

        if (filled($nick) && filled($name) && $nick !== $name) {
            return "{$name} ({$nick})";
        }

        return (string) ($name ?: $nick);
    }

    private function playerInitials(Geoguesser $player): string
    {
        $name = trim((string) ($player->user?->name ?? $player->username ?? ''));

        if ($name === '') {
            return '?';
        }

        $parts = preg_split('/\s+/', $name, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        if (count($parts) === 1) {
            return mb_strtoupper(mb_substr($parts[0], 0, 2));
        }

        return mb_strtoupper(mb_substr($parts[0], 0, 1).mb_substr($parts[1], 0, 1));
    }

    /**
     * @return array{level: mixed, xp: int|null, nextLevel: mixed, nextLevelXp: int|null, percent: int|null}
     */
    private function viewerProgress(?Geoguesser $geoguesser): array
    {
        $snapshots = $geoguesser?->challenges()
            ->whereNotNull('progress')
            ->orderByDesc('attempted_at')
            ->get() ?? collect();
        $progress = $snapshots->first()?->progress ?? [];
        $xp = $this->arrayValue($progress, 'xp');
        $nextLevelXp = $this->arrayValue($progress, 'nextLevelXp');
        $level = is_numeric($progress['level'] ?? null) ? (int) $progress['level'] : null;
        $levelXp = $this->arrayValue($progress, 'levelXp') ?? $this->previousLevelStartXp($snapshots, $level);
        $span = $nextLevelXp !== null && $levelXp !== null ? max($nextLevelXp - $levelXp, 1) : null;
        $earned = $xp !== null && $levelXp !== null ? max($xp - $levelXp, 0) : null;
        $percent = $span !== null && $earned !== null
            ? min(100, (int) round(100 * $earned / $span))
            : (($xp !== null || $level !== null) ? 50 : null);

        return [
            'level' => $progress['level'] ?? null,
            'xp' => $xp,
            'nextLevel' => $progress['nextLevel'] ?? null,
            'nextLevelXp' => $nextLevelXp,
            'percent' => $percent,
        ];
    }

    /**
     * @param  Collection<int, GeoguesserChallenge>  $snapshots
     */
    private function previousLevelStartXp(Collection $snapshots, ?int $level): ?int
    {
        if ($level === null) {
            return null;
        }

        foreach ($snapshots as $challenge) {
            $progress = $challenge->progress ?? [];
            $nextLevel = is_numeric($progress['nextLevel'] ?? null) ? (int) $progress['nextLevel'] : null;

            if ($nextLevel === $level) {
                return $this->arrayValue($progress, 'nextLevelXp');
            }

            $snapshotLevel = is_numeric($progress['level'] ?? null) ? (int) $progress['level'] : null;

            if ($snapshotLevel === $level) {
                $start = $this->arrayValue($progress, 'levelXp');

                if ($start !== null) {
                    return $start;
                }
            }
        }

        return null;
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
