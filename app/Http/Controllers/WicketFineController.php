<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreWicketFineRequest;
use App\Models\User;
use App\Models\WicketGroup;
use App\Services\Push\NotifyWicketFine;
use App\Services\Wickets\IssueWicketFine;
use Illuminate\Http\RedirectResponse;

class WicketFineController extends Controller
{
    public function store(
        StoreWicketFineRequest $request,
        WicketGroup $wicketGroup,
        IssueWicketFine $issueWicketFine,
        NotifyWicketFine $notifyWicketFine,
    ): RedirectResponse {
        $issuer = $request->user();
        $ids = $request->issuedToUserIds();
        $targets = User::query()->whereIn('id', $ids)->get()->keyBy('id');
        $conversions = 0;

        foreach ($ids as $id) {
            $target = $targets->get($id);

            if ($target === null) {
                continue;
            }

            $result = $issueWicketFine->handle(
                $wicketGroup,
                $issuer,
                $target,
                $request->type(),
                $request->validated('reason'),
                $request->integer('sips'),
            );

            $notifyWicketFine->handle($wicketGroup, $issuer, $target, $result['fine']);
            $conversions += $result['conversions'];
        }

        $status = $targets->count() === 1
            ? 'Fine given to '.$targets->first()->name.'.'
            : 'Fine given to '.$targets->count().' players.';

        if ($conversions === 1) {
            $status .= ' Their sips reached 8, so the system gave them a down down for accumulation.';
        } elseif ($conversions > 1) {
            $status .= ' Their sips reached 8, so the system gave them '.$conversions.' down downs for accumulation.';
        }

        return redirect()
            ->route('wickets.show', ['wicketGroup' => $wicketGroup, 'tab' => 'board'])
            ->with('status', $status);
    }
}
