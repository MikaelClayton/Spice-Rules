<?php

namespace App\Http\Controllers;

use App\Enums\PubGolfDrinkCategory;
use App\Http\Requests\StorePubGolfCrawlRequest;
use App\Models\PubGolfCrawl;
use App\Models\PubGolfParticipant;
use App\Services\Chat\BuildChat;
use App\Services\PubGolf\BuildPubGolfBoard;
use App\Services\PubGolf\PubGolfListedDrink;
use App\Services\PubGolf\StartPubGolfCrawl;
use App\Services\Timezone\ResolveDisplayTimezone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PubGolfCrawlController extends Controller
{
    public function index(Request $request, ResolveDisplayTimezone $resolveDisplayTimezone): View
    {
        $user = $request->user();
        $current = PubGolfCrawl::currentFor($user);
        $past = PubGolfParticipant::query()
            ->whereBelongsTo($user)
            ->whereNotNull('left_at')
            ->with('crawl')
            ->orderByDesc('left_at')
            ->orderByDesc('id')
            ->limit(20)
            ->get();

        return view('pub-golf.index', [
            'current' => $current,
            'past' => $past,
            'displayTimezone' => $resolveDisplayTimezone->name(),
        ]);
    }

    public function store(StorePubGolfCrawlRequest $request, StartPubGolfCrawl $startPubGolfCrawl): RedirectResponse
    {
        $crawl = $startPubGolfCrawl->handle($request->user(), $request->crawlName());

        return redirect()
            ->route('pub-golf.show', $crawl)
            ->with('status', 'Crawl started. Share the code so friends can join.');
    }

    public function show(
        Request $request,
        PubGolfCrawl $pubGolfCrawl,
        BuildPubGolfBoard $buildPubGolfBoard,
        BuildChat $buildChat,
    ): View|RedirectResponse {
        if (! $pubGolfCrawl->isActiveParticipant($request->user())) {
            return redirect()->route('pub-golf.recap.show', $pubGolfCrawl);
        }

        return view('pub-golf.show', [
            'crawl' => $pubGolfCrawl,
            'board' => $buildPubGolfBoard->handle($pubGolfCrawl, $request->user()),
            'chat' => $buildChat->handle($pubGolfCrawl, $request->user()),
            'categories' => PubGolfDrinkCategory::cases(),
            'drinks' => PubGolfListedDrink::grouped(),
            'viewerId' => $request->user()->id,
        ]);
    }
}
