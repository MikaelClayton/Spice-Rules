<?php

namespace App\Http\Controllers;

use App\Enums\WicketFineType;
use App\Http\Requests\StoreWicketGroupRequest;
use App\Http\Requests\UpdateWicketGroupRequest;
use App\Models\User;
use App\Models\WicketFine;
use App\Models\WicketGroup;
use App\Services\Wickets\BuildWicketActivity;
use App\Services\Wickets\BuildWicketStandings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class WicketGroupController extends Controller
{
    public function index(Request $request): View
    {
        $groups = $request->user()
            ->wicketGroups()
            ->withCount([
                'users',
                'fines as outstanding_fines_count' => fn ($query) => $query->whereNull('completed_at'),
            ])
            ->orderBy('name')
            ->orderBy('id')
            ->get();

        return view('wickets.index', [
            'groups' => $groups,
        ]);
    }

    public function create(): View
    {
        return view('wickets.create');
    }

    public function store(StoreWicketGroupRequest $request): RedirectResponse
    {
        $group = DB::transaction(function () use ($request): WicketGroup {
            $group = WicketGroup::query()->create([
                'user_id' => $request->user()->id,
                'name' => $request->validated('name'),
                'is_tournament' => $request->boolean('is_tournament'),
            ]);

            $group->users()->syncWithoutDetaching([$request->user()->id]);

            return $group;
        });

        return redirect()
            ->route('wickets.show', ['wicketGroup' => $group, 'tab' => 'people'])
            ->with('status', 'Group created. Add the rest of the crew.');
    }

    public function update(UpdateWicketGroupRequest $request, WicketGroup $wicketGroup): RedirectResponse
    {
        $wicketGroup->update([
            'is_tournament' => $request->boolean('is_tournament'),
        ]);

        return redirect()
            ->route('wickets.show', ['wicketGroup' => $wicketGroup, 'tab' => 'people'])
            ->with('status', $request->boolean('is_tournament')
                ? 'Tournament mode is on.'
                : 'Tournament mode is off.');
    }

    public function show(
        Request $request,
        WicketGroup $wicketGroup,
        BuildWicketActivity $buildWicketActivity,
        BuildWicketStandings $buildWicketStandings,
    ): View {
        abort_unless($wicketGroup->hasMember($request->user()), 403);

        $wicketGroup->load([
            'users' => fn ($query) => $query->orderBy('name')->orderBy('id'),
        ]);

        $viewer = $request->user();
        $hideOwnFines = $wicketGroup->hidesOwnFinesFrom($viewer);
        $activity = $buildWicketActivity->handle($wicketGroup);

        if ($hideOwnFines) {
            $activity = $activity
                ->filter(function (array $item) use ($viewer): bool {
                    if ($item['kind'] === 'drink') {
                        return $item['log']?->user_id === $viewer->id;
                    }

                    $fine = $item['fine'];

                    if ($fine === null || $fine->issued_to_user_id === $viewer->id) {
                        return false;
                    }

                    return $fine->issued_by_user_id === $viewer->id;
                })
                ->values();
        }

        $outstanding = $wicketGroup->fines()
            ->with(['issuedTo', 'issuedBy'])
            ->whereNull('completed_at')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        $standings = $buildWicketStandings->handle($wicketGroup, $viewer, $outstanding);
        $myOutstanding = $outstanding->where('issued_to_user_id', $viewer->id);
        $myRemainingSips = $myOutstanding->sum(fn (WicketFine $fine): int => $fine->remainingSips());
        $mySpecials = $myOutstanding
            ->filter(fn (WicketFine $fine): bool => $fine->type->isSip() === false)
            ->values();
        $mySpecialCounts = $hideOwnFines
            ? $buildWicketStandings->hiddenSpecials()
            : $buildWicketStandings->countSpecials($mySpecials);

        $availableUsers = User::query()
            ->whereNotIn('id', $wicketGroup->users->modelKeys())
            ->orderBy('name')
            ->orderBy('id')
            ->get();

        return view('wickets.show', [
            'group' => $wicketGroup,
            'activity' => $activity,
            'standings' => $standings,
            'hideOwnFines' => $hideOwnFines,
            'myRemainingSips' => $myRemainingSips,
            'mySipFines' => $hideOwnFines
                ? collect()
                : $myOutstanding
                    ->filter(fn (WicketFine $fine): bool => $fine->type->isSip())
                    ->values(),
            'mySpecials' => $hideOwnFines ? collect() : $mySpecials,
            'mySpecialCounts' => $mySpecialCounts,
            'fineTypes' => WicketFineType::specials(),
            'maxSipFine' => WicketFineType::MAX_SIP_FINE,
            'isOwner' => $wicketGroup->isOwnedBy($viewer),
            'availableUsers' => $availableUsers,
            'activeTab' => $this->activeTab($request),
        ]);
    }

    private function activeTab(Request $request): string
    {
        $tab = $request->string('tab')->toString();

        return in_array($tab, ['board', 'fine', 'drink', 'people'], true) ? $tab : 'board';
    }
}
