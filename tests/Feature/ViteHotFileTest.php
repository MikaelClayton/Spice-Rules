<?php

namespace Tests\Feature;

use App\Providers\AppServiceProvider;
use Illuminate\Support\Facades\Vite;
use ReflectionMethod;
use Tests\TestCase;

class ViteHotFileTest extends TestCase
{
    public function test_production_does_not_read_the_vite_hot_file(): void
    {
        $hot = public_path('hot');
        file_put_contents($hot, 'http://[::1]:5173');

        try {
            $this->assertTrue(Vite::isRunningHot());

            $this->app['env'] = 'production';
            $method = new ReflectionMethod(AppServiceProvider::class, 'ignoreViteHotFileInProduction');
            $method->invoke(new AppServiceProvider($this->app));

            $this->assertFalse(Vite::isRunningHot());
        } finally {
            $this->app['env'] = 'testing';
            Vite::useHotFile(public_path('hot'));

            if (is_file($hot)) {
                unlink($hot);
            }
        }
    }
}
