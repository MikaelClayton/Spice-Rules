<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePubGolfCrawlJoinRequest;
use App\Services\PubGolf\JoinPubGolfCrawl;
use Illuminate\Http\RedirectResponse;

class PubGolfCrawlJoinController extends Controller
{
    public function store(StorePubGolfCrawlJoinRequest $request, JoinPubGolfCrawl $joinPubGolfCrawl): RedirectResponse
    {
        $crawl = $joinPubGolfCrawl->handle($request->user(), $request->joinCode());

        return redirect()
            ->route('pub-golf.show', $crawl)
            ->with('status', 'You are on the crawl.');
    }
}
