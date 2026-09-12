<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePubGolfDrinkUndoRequest;
use App\Models\PubGolfCrawl;
use App\Services\PubGolf\UndoPubGolfDrink;
use Illuminate\Http\RedirectResponse;

class PubGolfDrinkUndoController extends Controller
{
    public function store(
        StorePubGolfDrinkUndoRequest $request,
        PubGolfCrawl $pubGolfCrawl,
        UndoPubGolfDrink $undoPubGolfDrink,
    ): RedirectResponse {
        $log = $undoPubGolfDrink->handle($pubGolfCrawl, $request->user());

        return redirect()
            ->route('pub-golf.show', $pubGolfCrawl)
            ->with('status', $log->listed()->label.' undone.');
    }
}
