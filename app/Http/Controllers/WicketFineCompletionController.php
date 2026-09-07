<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreWicketFineCompletionRequest;
use App\Models\WicketFine;
use App\Models\WicketGroup;
use Illuminate\Http\RedirectResponse;

class WicketFineCompletionController extends Controller
{
    public function store(
        StoreWicketFineCompletionRequest $request,
        WicketGroup $wicketGroup,
        WicketFine $fine,
    ): RedirectResponse {
        $fine->completed_at = now();
        $fine->save();

        return redirect()
            ->route('wickets.show', ['wicketGroup' => $wicketGroup, 'tab' => 'drink'])
            ->with('status', $fine->type->label().' done.');
    }
}
