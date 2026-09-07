<?php

namespace App\Http\Controllers;

use App\Enums\WicketFineType;
use App\Http\Requests\StoreWicketGroupRequest;
use App\Models\User;
use App\Models\WicketFine;
use App\Models\WicketGroup;
use App\Services\Wickets\BuildWicketActivity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
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
            ]);

            $group->users()->syncWithoutDetaching([$request->user()->id]);

            return $group;
        });

        return redirect()
            ->route('wickets.show', ['wicketGroup' => $group, 'tab' => 'people'])
            ->with('status', 'Group created. Add the rest of the crew.');
    }

    public function show(Request $request, WicketGroup $wicketGroup, BuildWicketActivity $buildWicketActivity): View
    {
        abort_unless($wicketGroup->hasMember($request->user()), 403);

        $wicketGroup->load([
            'users' => fn ($query) => $query->orderBy('name')->orderBy('id'),
        ]);

        $activity = $buildWicketActivity->handle($wicketGroup);

        $outstanding = $wicketGroup->fines()
            ->with(['issuedTo', 'issuedBy'])
            ->whereNull('completed_at')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        $standings = $wicketGroup->users
            ->map(function (User $user) use ($outstanding): array {
                $userFines = $outstanding->where('issued_to_user_id', $user->id);
                $specials = $userFines
                    ->filter(fn (WicketFine $fine): bool => $fine->type->isSip() === false)
                    ->values();

                return [
                    'user' => $user,
                    'sips' => $userFines->sum(fn (WicketFine $fine): int => $fine->remainingSips()),
                    'specials' => $this->countSpecials($specials),
                    'specialCount' => $specials->count(),
                    'fines' => $userFines->values(),
                ];
            })
            ->sortBy([
                fn (array $left, array $right): int => $right['sips'] <=> $left['sips'],
                fn (array $left, array $right): int => $right['specialCount'] <=> $left['specialCount'],
                fn (array $left, array $right): int => $left['user']->name <=> $right['user']->name,
                fn (array $left, array $right): int => $left['user']->id <=> $right['user']->id,
            ])
            ->values();

        $viewer = $request->user();
        $myOutstanding = $outstanding->where('issued_to_user_id', $viewer->id);
        $myRemainingSips = $myOutstanding->sum(fn (WicketFine $fine): int => $fine->remainingSips());
        $mySpecials = $myOutstanding
            ->filter(fn (WicketFine $fine): bool => $fine->type->isSip() === false)
            ->values();

        $availableUsers = User::query()
            ->whereNotIn('id', $wicketGroup->users->modelKeys())
            ->orderBy('name')
            ->orderBy('id')
            ->get();

        return view('wickets.show', [
            'group' => $wicketGroup,
            'activity' => $activity,
            'standings' => $standings,
            'myRemainingSips' => $myRemainingSips,
            'mySipFines' => $myOutstanding
                ->filter(fn (WicketFine $fine): bool => $fine->type->isSip())
                ->values(),
            'mySpecials' => $mySpecials,
            'mySpecialCounts' => $this->countSpecials($mySpecials),
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

    /**
     * @param  Collection<int, WicketFine>  $specials
     * @return Collection<int, array{type: WicketFineType, count: int}>
     */
    private function countSpecials(Collection $specials): Collection
    {
        $order = array_flip(array_map(
            fn (WicketFineType $type): string => $type->value,
            WicketFineType::specials(),
        ));

        return $specials
            ->groupBy(fn (WicketFine $fine): string => $fine->type->value)
            ->map(fn (Collection $group): array => [
                'type' => $group->first()->type,
                'count' => $group->count(),
            ])
            ->sortBy(fn (array $row): int => $order[$row['type']->value] ?? 99)
            ->values();
    }
}
