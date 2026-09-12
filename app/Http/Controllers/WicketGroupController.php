<?php

namespace App\Http\Controllers;

use App\Enums\WicketFineType;
use App\Http\Requests\DestroyWicketGroupRequest;
use App\Http\Requests\StoreWicketGroupRequest;
use App\Http\Requests\UpdateWicketGroupRequest;
use App\Models\User;
use App\Models\WicketGroup;
use App\Services\Wickets\BuildWicketBoard;
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
            ->active()
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
                'notify_all_on_fine' => $request->boolean('notify_all_on_fine'),
                'is_active' => true,
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
            'notify_all_on_fine' => $request->boolean('notify_all_on_fine'),
        ]);

        return redirect()
            ->route('wickets.show', ['wicketGroup' => $wicketGroup, 'tab' => 'people'])
            ->with('status', 'Group settings saved.');
    }

    public function destroy(DestroyWicketGroupRequest $request, WicketGroup $wicketGroup): RedirectResponse
    {
        $wicketGroup->update([
            'is_active' => false,
        ]);

        return redirect()
            ->route('wickets.index')
            ->with('status', 'Group deleted.');
    }

    public function show(
        Request $request,
        WicketGroup $wicketGroup,
        BuildWicketBoard $buildWicketBoard,
    ): View {
        $viewer = $request->user();
        $board = $buildWicketBoard->handle($wicketGroup, $viewer);

        $availableUsers = User::query()
            ->whereNotIn('id', $wicketGroup->users->modelKeys())
            ->orderBy('name')
            ->orderBy('id')
            ->get();

        return view('wickets.show', [
            'group' => $wicketGroup,
            ...$board,
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
