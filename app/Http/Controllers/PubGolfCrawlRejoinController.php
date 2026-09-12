<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePubGolfCrawlRejoinRequest;
use App\Models\PubGolfCrawl;
use App\Services\PubGolf\RejoinPubGolfCrawl;
use Illuminate\Http\RedirectResponse;

class PubGolfCrawlRejoinController extends Controller
{
    public function store(
        StorePubGolfCrawlRejoinRequest $request,
        PubGolfCrawl $pubGolfCrawl,
        RejoinPubGolfCrawl $rejoinPubGolfCrawl,
    ): RedirectResponse {
        $rejoinPubGolfCrawl->handle($pubGolfCrawl, $request->user());

        return redirect()
            ->route('pub-golf.show', $pubGolfCrawl)
            ->with('status', 'You are back on the crawl.');
    }
}
