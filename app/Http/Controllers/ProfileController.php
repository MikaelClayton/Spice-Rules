<?php

namespace App\Http\Controllers;

use App\Http\Requests\BrowseGeoguessrChallengesRequest;
use App\Http\Requests\ShareGeoguessrChallengeAsTeamRequest;
use App\Http\Requests\UpdateGeoguessrSettingsRequest;
use App\Http\Requests\UpdateProfileRequest;
use App\Models\Geoguesser;
use App\Models\GeoguesserChallenge;
use App\Services\Geoguessr\GeoguessrClient;
use App\Services\Geoguessr\ShareGeoguessrChallengeAsTeam;
use App\Services\Geoguessr\SyncActiveGeoguessers;
use App\Services\Push\FirebaseConfig;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;

class ProfileController extends Controller
{
    private const CHALLENGE_PAGE_SIZE = 25;

    public function edit(FirebaseConfig $firebase): View
    {
        $user = request()->user();
        $canBrowseChallenges = $user->canBrowseGeoguessrChallenges();
        $challengePage = $canBrowseChallenges
            ? $this->challengePage()
            : ['challenges' => [], 'hasMore' => false];

        return view('profile.edit', [
            'user' => $user,
            'geoguesser' => $user->geoguesser,
            'activeTab' => request()->string('tab')->toString() === 'geoguessr' ? 'geoguessr' : 'account',
            'canBrowseChallenges' => $canBrowseChallenges,
            'challengePlayers' => $canBrowseChallenges ? $this->challengePlayers() : [],
            'challengeGrid' => $challengePage['challenges'],
            'challengeHasMore' => $challengePage['hasMore'],
            'shareTargets' => $canBrowseChallenges ? $this->shareTargets() : [],
            'firebase' => $firebase,
        ]);
    }

    public function update(UpdateProfileRequest $request): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validated();

        $user->fill([
            'name' => $data['name'],
            'email' => $data['email'],
            'color' => $data['color'] ?? $user->color,
        ]);

        if (filled($data['password'] ?? null)) {
            $user->password = $data['password'];
        }

        $user->save();

        return redirect()
            ->route('profile.edit')
            ->with('status', 'Your details were saved.');
    }

    public function updateGeoguessr(UpdateGeoguessrSettingsRequest $request, GeoguessrClient $client): RedirectResponse
    {
        $ncfa = GeoguessrClient::normalizeNcfa((string) $request->validated('ncfa'));

        $geoguesser = Geoguesser::query()->firstOrNew(
            ['user_id' => $request->user()->id],
            ['username' => $request->user()->name],
        );

        $geoguesser->ncfa = $ncfa;

        try {
            $geoguesser->applyFromProfile(
                $client->using('cookie_test', geoguesserId: $geoguesser->id)->profile($ncfa),
            );
            $geoguesser->is_active = true;
            $geoguesser->save();
        } catch (RequestException $exception) {
            $geoguesser->is_active = false;
            $geoguesser->save();

            Log::warning('GeoGuessr cookie test was rejected', [
                'user_id' => $request->user()->id,
                'status' => $exception->response?->status(),
            ]);

            return redirect()
                ->route('profile.edit', ['tab' => 'geoguessr'])
                ->withInput()
                ->withErrors(['ncfa' => 'GeoGuessr rejected this _ncfa cookie. Copy the Value again from DevTools and try Test.']);
        } catch (ConnectionException $exception) {
            $geoguesser->save();

            Log::warning('GeoGuessr cookie test could not connect', [
                'user_id' => $request->user()->id,
                'message' => $exception->getMessage(),
            ]);

            return redirect()
                ->route('profile.edit', ['tab' => 'geoguessr'])
                ->withInput()
                ->withErrors(['ncfa' => 'This app server could not reach GeoGuessr. Run `php artisan serve` in your own terminal (not Cursor) and try Test again, or run `php artisan geoguessr:sync`.']);
        } catch (\Throwable $exception) {
            $geoguesser->save();

            Log::error('GeoGuessr cookie test failed', [
                'user_id' => $request->user()->id,
                'message' => $exception->getMessage(),
            ]);

            return redirect()
                ->route('profile.edit', ['tab' => 'geoguessr'])
                ->withInput()
                ->withErrors(['ncfa' => 'Could not verify the cookie. Check the log and try Test again.']);
        }

        return redirect()
            ->route('profile.edit', ['tab' => 'geoguessr'])
            ->with('status', 'GeoGuessr is active. Profile data was saved.');
    }

    public function syncGeoguessr(Request $request, SyncActiveGeoguessers $sync): RedirectResponse
    {
        $geoguesser = $request->user()->geoguesser;

        if ($geoguesser === null || ! $geoguesser->is_active || blank($geoguesser->ncfa)) {
            return redirect()
                ->route('profile.edit', ['tab' => 'geoguessr'])
                ->withErrors(['sync' => 'Connect an active GeoGuessr cookie before syncing.']);
        }

        $throttleKey = 'geoguessr-sync:'.$request->user()->id;

        if (RateLimiter::tooManyAttempts($throttleKey, 1)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            return redirect()
                ->route('profile.edit', ['tab' => 'geoguessr'])
                ->withErrors(['sync' => "Wait {$seconds} seconds before syncing again."]);
        }

        RateLimiter::hit($throttleKey, 30);

        try {
            $sync->sync($geoguesser);
        } catch (RequestException $exception) {
            Log::warning('GeoGuessr profile sync was rejected', [
                'user_id' => $request->user()->id,
                'geoguesser_id' => $geoguesser->id,
                'status' => $exception->response?->status(),
            ]);

            return redirect()
                ->route('profile.edit', ['tab' => 'geoguessr'])
                ->withErrors(['sync' => 'GeoGuessr rejected this cookie. Update it and press Test, then try Sync again.']);
        } catch (ConnectionException $exception) {
            Log::warning('GeoGuessr profile sync could not connect', [
                'user_id' => $request->user()->id,
                'geoguesser_id' => $geoguesser->id,
                'message' => $exception->getMessage(),
            ]);

            return redirect()
                ->route('profile.edit', ['tab' => 'geoguessr'])
                ->withErrors(['sync' => 'Could not reach GeoGuessr. Try Sync again in a moment.']);
        } catch (\Throwable $exception) {
            Log::error('GeoGuessr profile sync failed', [
                'user_id' => $request->user()->id,
                'geoguesser_id' => $geoguesser->id,
                'message' => $exception->getMessage(),
            ]);

            return redirect()
                ->route('profile.edit', ['tab' => 'geoguessr'])
                ->withErrors(['sync' => 'Could not sync GeoGuessr. Check the log and try again.']);
        }

        return redirect()
            ->route('profile.edit', ['tab' => 'geoguessr'])
            ->with('status', 'GeoGuessr scores were synced.');
    }

    public function geoguessrChallenges(BrowseGeoguessrChallengesRequest $request): JsonResponse
    {
        return response()->json($this->challengePage($request->playerId(), $request->pageNumber()));
    }

    public function shareGeoguessrChallenge(
        ShareGeoguessrChallengeAsTeamRequest $request,
        ShareGeoguessrChallengeAsTeam $share,
    ): JsonResponse {
        $source = GeoguesserChallenge::query()
            ->with(['geoguesser.user', 'rounds'])
            ->findOrFail($request->integer('challenge_id'));
        $ids = $request->geoguesserIds();
        $targets = Geoguesser::query()
            ->with('user')
            ->whereIn('id', $ids)
            ->get()
            ->sortBy(fn (Geoguesser $player): int => (int) array_search($player->id, $ids, true))
            ->values();

        $share->handle($source, $targets);

        $names = $targets->map(fn (Geoguesser $player): string => $player->displayName());

        return response()->json([
            'message' => $names->count() === 1
                ? "Shared this challenge as a team with {$names->first()}."
                : 'Shared this challenge as a team with '.$names->join(', ').'.',
            'isDoneAsTeam' => true,
            'geoguesserIds' => $targets->pluck('id')->values()->all(),
        ]);
    }

    /**
     * @return list<array{id: int, label: string, color: string}>
     */
    private function challengePlayers(): array
    {
        return Geoguesser::query()
            ->with('user')
            ->whereHas('challenges')
            ->orderBy('username')
            ->orderBy('id')
            ->get()
            ->map(fn (Geoguesser $player): array => [
                'id' => $player->id,
                'label' => $player->displayName(),
                'color' => $player->boardColor(),
            ])
            ->sortBy('label', SORT_NATURAL | SORT_FLAG_CASE)
            ->values()
            ->all();
    }

    /**
     * @return list<array{id: int, label: string, color: string, dates: list<string>}>
     */
    private function shareTargets(): array
    {
        $datesByPlayer = [];

        GeoguesserChallenge::query()
            ->whereNotNull('attempted_at')
            ->get(['geoguesser_id', 'attempted_at'])
            ->each(function (GeoguesserChallenge $challenge) use (&$datesByPlayer): void {
                $date = $challenge->attempted_at?->toDateString();

                if ($date === null) {
                    return;
                }

                $datesByPlayer[$challenge->geoguesser_id][$date] = $date;
            });

        return Geoguesser::query()
            ->with('user')
            ->where('is_active', true)
            ->orderBy('username')
            ->orderBy('id')
            ->get()
            ->map(fn (Geoguesser $player): array => [
                'id' => $player->id,
                'label' => $player->displayName(),
                'color' => $player->boardColor(),
                'dates' => array_values($datesByPlayer[$player->id] ?? []),
            ])
            ->sortBy('label', SORT_NATURAL | SORT_FLAG_CASE)
            ->values()
            ->all();
    }

    /**
     * @return array{
     *     challenges: list<array{
     *         id: int,
     *         playerId: int,
     *         player: string,
     *         color: string,
     *         date: string,
     *         dateKey: string|null,
     *         map: string,
     *         score: int|null,
     *         distance: int|null,
     *         steps: int|null,
     *         isDoneAsTeam: bool
     *     }>,
     *     hasMore: bool
     * }
     */
    private function challengePage(?int $playerId = null, int $page = 1): array
    {
        $query = GeoguesserChallenge::query()
            ->with('geoguesser.user')
            ->orderByDesc('attempted_at')
            ->orderByDesc('id');

        if ($playerId !== null) {
            $query->where('geoguesser_id', $playerId);
        }

        $paginator = $query->simplePaginate(self::CHALLENGE_PAGE_SIZE, page: $page);

        return [
            'challenges' => $paginator
                ->getCollection()
                ->map(fn (GeoguesserChallenge $challenge): array => $this->challengeCard($challenge))
                ->values()
                ->all(),
            'hasMore' => $paginator->hasMorePages(),
        ];
    }

    /**
     * @return array{
     *     id: int,
     *     playerId: int,
     *     player: string,
     *     color: string,
     *     date: string,
     *     dateKey: string|null,
     *     map: string,
     *     score: int|null,
     *     distance: int|null,
     *     steps: int|null,
     *     isDoneAsTeam: bool
     * }
     */
    private function challengeCard(GeoguesserChallenge $challenge): array
    {
        return [
            'id' => $challenge->id,
            'playerId' => (int) $challenge->geoguesser_id,
            'player' => $challenge->geoguesser?->displayName() ?? 'Unknown',
            'color' => $challenge->geoguesser?->boardColor() ?? '#283030',
            'date' => $challenge->attempted_at?->toFormattedDateString() ?? 'Unknown date',
            'dateKey' => $challenge->attempted_at?->toDateString(),
            'map' => $challenge->map_name ?: 'World',
            'score' => $challenge->total_score,
            'distance' => $challenge->total_distance,
            'steps' => $challenge->total_steps_count,
            'isDoneAsTeam' => (bool) $challenge->is_done_as_team,
        ];
    }
}
