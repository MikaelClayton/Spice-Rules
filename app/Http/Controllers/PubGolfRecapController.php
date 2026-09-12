<?php

namespace App\Http\Controllers;

use App\Models\PubGolfCrawl;
use App\Services\Chat\BuildChat;
use App\Services\PubGolf\BuildPubGolfRecap;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PubGolfRecapController extends Controller
{
    public function show(
        Request $request,
        PubGolfCrawl $pubGolfCrawl,
        BuildPubGolfRecap $buildPubGolfRecap,
        BuildChat $buildChat,
    ): View|RedirectResponse {
        if ($pubGolfCrawl->isActiveParticipant($request->user())) {
            return redirect()->route('pub-golf.show', $pubGolfCrawl);
        }

        return view('pub-golf.recap', [
            'crawl' => $pubGolfCrawl,
            'recap' => $buildPubGolfRecap->handle($pubGolfCrawl, $request->user()),
            'chat' => $buildChat->handle($pubGolfCrawl, $request->user()),
        ]);
    }
}
