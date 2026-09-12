<?php

namespace Tests\Unit\Services\Timezone;

use App\Services\Timezone\ResolveDisplayTimezone;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Tests\TestCase;

class ResolveDisplayTimezoneTest extends TestCase
{
    public function test_it_uses_a_valid_browser_timezone_cookie(): void
    {
        $request = Request::create('/');
        $request->cookies->set(ResolveDisplayTimezone::COOKIE, 'Europe/London');

        $this->assertSame('Europe/London', app(ResolveDisplayTimezone::class)->fromRequest($request));
    }

    public function test_it_falls_back_when_the_cookie_is_not_a_real_timezone(): void
    {
        $request = Request::create('/');
        $request->cookies->set(ResolveDisplayTimezone::COOKIE, 'Not/AZone');

        $this->assertSame('Africa/Johannesburg', app(ResolveDisplayTimezone::class)->fromRequest($request));
    }

    public function test_it_reads_the_timezone_from_request_context(): void
    {
        Context::add(ResolveDisplayTimezone::CONTEXT_KEY, 'Pacific/Auckland');

        $this->assertSame('Pacific/Auckland', app(ResolveDisplayTimezone::class)->name());
    }
}
