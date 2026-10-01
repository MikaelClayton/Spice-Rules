<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePubGolfCrawlJoinRequest;
use App\Services\PubGolf\JoinPubGolfCrawl;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PubGolfCrawlJoinController extends Controller
{
    public function show(Request $request, string $code, JoinPubGolfCrawl $joinPubGolfCrawl): RedirectResponse
    {
        $code = strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', $code));

        if (strlen($code) !== 6) {
            return redirect()
                ->route('pub-golf.index')
                ->withErrors(['code' => 'Crawl codes are 6 characters.']);
        }

        try {
            $crawl = $joinPubGolfCrawl->handle($request->user(), $code);
        } catch (ValidationException $exception) {
            return redirect()
                ->route('pub-golf.index')
                ->withErrors($exception->errors());
        }

        return redirect()
            ->route('pub-golf.show', $crawl)
            ->with('status', 'You are on the crawl.');
    }

    public function store(StorePubGolfCrawlJoinRequest $request, JoinPubGolfCrawl $joinPubGolfCrawl): RedirectResponse
    {
        $crawl = $joinPubGolfCrawl->handle($request->user(), $request->joinCode());

        return redirect()
            ->route('pub-golf.show', $crawl)
            ->with('status', 'You are on the crawl.');
    }
}
