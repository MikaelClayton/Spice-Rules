<?php

namespace App\Http\Controllers;

use App\Http\Requests\DestroyPubGolfCustomDrinkRequest;
use App\Http\Requests\StorePubGolfCustomDrinkRequest;
use App\Models\PubGolfCrawl;
use App\Models\PubGolfCustomDrink;
use App\Services\PubGolf\AddPubGolfCustomDrink;
use App\Services\PubGolf\RemovePubGolfCustomDrink;
use Illuminate\Http\RedirectResponse;

class PubGolfCustomDrinkController extends Controller
{
    public function store(
        StorePubGolfCustomDrinkRequest $request,
        PubGolfCrawl $pubGolfCrawl,
        AddPubGolfCustomDrink $addPubGolfCustomDrink,
    ): RedirectResponse {
        $drink = $addPubGolfCustomDrink->handle(
            $request->user(),
            $request->validated('name'),
            $request->category(),
            $request->photo(),
        );

        return redirect()
            ->route('pub-golf.show', $pubGolfCrawl)
            ->with('status', $drink->name.' added to the list.');
    }

    public function destroy(
        DestroyPubGolfCustomDrinkRequest $request,
        PubGolfCrawl $pubGolfCrawl,
        PubGolfCustomDrink $pubGolfCustomDrink,
        RemovePubGolfCustomDrink $removePubGolfCustomDrink,
    ): RedirectResponse {
        $removePubGolfCustomDrink->handle($request->user(), $pubGolfCustomDrink);

        return redirect()
            ->route('pub-golf.show', $pubGolfCrawl)
            ->with('status', $pubGolfCustomDrink->name.' removed from the list.');
    }
}
