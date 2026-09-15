<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePubGolfDrinkLogRequest;
use App\Models\PubGolfCrawl;
use App\Services\PubGolf\LogPubGolfDrink;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

class PubGolfDrinkLogController extends Controller
{
    public function store(
        StorePubGolfDrinkLogRequest $request,
        PubGolfCrawl $pubGolfCrawl,
        LogPubGolfDrink $logPubGolfDrink,
    ): JsonResponse|RedirectResponse {
        $drink = $request->listedDrink();
        $logPubGolfDrink->handle(
            $pubGolfCrawl,
            $request->user(),
            $drink,
            $request->latitude(),
            $request->longitude(),
        );

        $status = $drink->label.' logged.';

        if ($request->wantsJson()) {
            $request->session()->flash('status', $status);

            return response()->json([
                'redirect' => route('pub-golf.show', $pubGolfCrawl),
            ]);
        }

        return redirect()
            ->route('pub-golf.show', $pubGolfCrawl)
            ->with('status', $status);
    }
}
