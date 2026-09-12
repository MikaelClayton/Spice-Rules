<?php

namespace App\Http\Middleware;

use App\Services\Timezone\ResolveDisplayTimezone;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Symfony\Component\HttpFoundation\Response;

class ShareDisplayTimezone
{
    public function __construct(private ResolveDisplayTimezone $resolveDisplayTimezone) {}

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        Context::add(
            ResolveDisplayTimezone::CONTEXT_KEY,
            $this->resolveDisplayTimezone->fromRequest($request),
        );

        return $next($request);
    }
}
