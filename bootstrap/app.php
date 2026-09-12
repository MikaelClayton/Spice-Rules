<?php

use App\Http\Middleware\ShareDisplayTimezone;
use App\Models\PubGolfCrawl;
use App\Models\WicketGroup;
use App\Services\Timezone\ResolveDisplayTimezone;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn () => route('dashboard'));
        $middleware->encryptCookies(except: [
            ResolveDisplayTimezone::COOKIE,
        ]);
        $middleware->web(append: [
            ShareDisplayTimezone::class,
        ]);
    })
    ->withSchedule(function (Schedule $schedule): void {
        $schedule->command('geoguessr:sync')->everyThirtyMinutes();
        $schedule->command('pub-golf:end-stale')
            ->everyFifteenMinutes()
            ->withoutOverlapping();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->render(function (NotFoundHttpException $exception, Request $request) {
            $previous = $exception->getPrevious();

            if (
                $previous instanceof ModelNotFoundException
                && in_array($previous->getModel(), [WicketGroup::class, PubGolfCrawl::class], true)
                && $request->user() !== null
            ) {
                return $previous->getModel() === PubGolfCrawl::class
                    ? redirect()->route('pub-golf.index')
                    : redirect()->route('wickets.index');
            }
        });
    })->create();
