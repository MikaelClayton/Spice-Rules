<?php

namespace App\Http\Middleware;

use App\Models\WicketGroup;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureWicketGroupMember
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $group = $request->route('wicketGroup');

        if ($group instanceof WicketGroup && $group->isActive() && $group->hasMember($request->user())) {
            return $next($request);
        }

        return redirect()->route('wickets.index');
    }
}
