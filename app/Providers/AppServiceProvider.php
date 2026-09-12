<?php

namespace App\Providers;

use App\Models\PubGolfCrawl;
use App\Services\Mail\ResendClientFactory;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Mail\Transport\ResendTransport;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Relation::enforceMorphMap([
            'pub_golf_crawl' => PubGolfCrawl::class,
        ]);

        Mail::extend('resend', function (array $config): ResendTransport {
            return new ResendTransport(
                $this->app->make(ResendClientFactory::class)->make(
                    $config['key'] ?? $this->app['config']->get('services.resend.key'),
                ),
            );
        });
    }
}
