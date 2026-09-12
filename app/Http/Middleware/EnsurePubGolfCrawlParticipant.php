<?php

namespace App\Http\Middleware;

use App\Models\PubGolfCrawl;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePubGolfCrawlParticipant
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $crawl = $request->route('pubGolfCrawl');

        if ($crawl instanceof PubGolfCrawl && $crawl->hasParticipant($request->user())) {
            return $next($request);
        }

        return redirect()->route('pub-golf.index');
    }
}
