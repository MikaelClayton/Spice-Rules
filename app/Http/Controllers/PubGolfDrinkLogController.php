<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePubGolfDrinkLogRequest;
use App\Models\PubGolfCrawl;
use App\Services\PubGolf\LogPubGolfDrink;
use Illuminate\Http\RedirectResponse;

class PubGolfDrinkLogController extends Controller
{
    public function store(
        StorePubGolfDrinkLogRequest $request,
        PubGolfCrawl $pubGolfCrawl,
        LogPubGolfDrink $logPubGolfDrink,
    ): RedirectResponse {
        $drink = $request->listedDrink();
        $logPubGolfDrink->handle($pubGolfCrawl, $request->user(), $drink);

        return redirect()
            ->route('pub-golf.show', $pubGolfCrawl)
            ->with('status', $drink->label.' logged.');
    }
}
