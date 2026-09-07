<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreWicketSipLogRequest;
use App\Models\WicketGroup;
use App\Services\Wickets\RecordWicketSips;
use Illuminate\Http\RedirectResponse;

class WicketSipLogController extends Controller
{
    public function store(
        StoreWicketSipLogRequest $request,
        WicketGroup $wicketGroup,
        RecordWicketSips $recordWicketSips,
    ): RedirectResponse {
        $log = $recordWicketSips->handle(
            $wicketGroup,
            $request->user(),
            $request->integer('sips'),
        );

        $status = $log->sips === 1
            ? '1 sip drunk.'
            : $log->sips.' sips drunk.';

        return redirect()
            ->route('wickets.show', ['wicketGroup' => $wicketGroup, 'tab' => 'drink'])
            ->with('status', $status);
    }
}
