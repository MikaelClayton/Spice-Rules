<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePubGolfCrawlLeaveRequest;
use App\Models\PubGolfCrawl;
use App\Services\PubGolf\LeavePubGolfCrawl;
use Illuminate\Http\RedirectResponse;

class PubGolfCrawlLeaveController extends Controller
{
    public function store(
        StorePubGolfCrawlLeaveRequest $request,
        PubGolfCrawl $pubGolfCrawl,
        LeavePubGolfCrawl $leavePubGolfCrawl,
    ): RedirectResponse {
        $leavePubGolfCrawl->handle($pubGolfCrawl, $request->user());

        $pubGolfCrawl->refresh();

        $status = $pubGolfCrawl->isOpen()
            ? 'You called it. The crawl stays open for everyone still out.'
            : 'You were the last one out. The crawl is wrapped.';

        return redirect()
            ->route('pub-golf.recap.show', $pubGolfCrawl)
            ->with('status', $status);
    }
}
