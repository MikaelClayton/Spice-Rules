<?php

namespace App\Http\Controllers;

use App\Http\Requests\DestroyWicketGroupMemberRequest;
use App\Http\Requests\StoreWicketGroupMemberRequest;
use App\Http\Requests\UpdateWicketGroupMemberRequest;
use App\Models\User;
use App\Models\WicketGroup;
use Illuminate\Http\RedirectResponse;

class WicketGroupMemberController extends Controller
{
    public function store(StoreWicketGroupMemberRequest $request, WicketGroup $wicketGroup): RedirectResponse
    {
        $userIds = $request->userIds();
        $wicketGroup->users()->syncWithoutDetaching($userIds);

        $status = count($userIds) === 1
            ? 'Player added.'
            : 'Players added.';

        return redirect()
            ->route('wickets.show', ['wicketGroup' => $wicketGroup, 'tab' => 'people'])
            ->with('status', $status);
    }

    public function destroy(DestroyWicketGroupMemberRequest $request, WicketGroup $wicketGroup, User $user): RedirectResponse
    {
        $wicketGroup->users()->detach($user->id);

        return redirect()
            ->route('wickets.show', ['wicketGroup' => $wicketGroup, 'tab' => 'people'])
            ->with('status', $user->name.' was removed.');
    }

    public function update(UpdateWicketGroupMemberRequest $request, WicketGroup $wicketGroup, User $user): RedirectResponse
    {
        $role = $request->role();
        $wicketGroup->users()->updateExistingPivot($user->id, [
            'role' => $role->value,
        ]);

        return redirect()
            ->route('wickets.show', ['wicketGroup' => $wicketGroup, 'tab' => 'people'])
            ->with('status', $role->isFinesMaster()
                ? $user->name.' is now Fines Master.'
                : $user->name.' is a player again.');
    }
}
