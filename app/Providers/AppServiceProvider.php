<?php

namespace App\Providers;

use App\Services\AiWorkerClient;
use App\Storage\DatabaseAdapter;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(AiWorkerClient::class, fn () => new AiWorkerClient());
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Force correct URL generation in production (behind Cloudflare SSL termination).
        // Without forceRootUrl, internal port 8080 leaks into generated URLs because
        // nginx forwards X-Forwarded-Port: 8080. This breaks Livewire AJAX calls
        // since the browser can only reach port 443 via Cloudflare.
        if ($this->app->environment('production', 'staging', 'sandbox')) {
            URL::forceScheme('https');
            URL::forceRootUrl(config('app.url'));
        }
        Event::listen(
            \SocialiteProviders\Manager\SocialiteWasCalled::class,
            \SocialiteProviders\Azure\AzureExtendSocialite::class.'@handle'
        );

        \Illuminate\Support\Facades\RateLimiter::for('tito', function ($request) {
            return \Illuminate\Cache\RateLimiting\Limit::perMinute(200)->by($request->ip());
        });
        \Illuminate\Support\Facades\RateLimiter::for('magic-link', function ($request) {
            return \Illuminate\Cache\RateLimiting\Limit::perMinute(5)->by($request->ip());
        });
        // C2: Rate-limit public signing routes to prevent brute-force / spam
        \Illuminate\Support\Facades\RateLimiter::for('signing', function ($request) {
            return \Illuminate\Cache\RateLimiting\Limit::perMinute(10)->by($request->ip());
        });

        // Register 'database' filesystem driver for MySQL BLOB storage
        Storage::extend('database', function ($app, $config) {
            $adapter = new DatabaseAdapter();
            $flysystem = new \League\Flysystem\Filesystem($adapter);

            return new \Illuminate\Filesystem\FilesystemAdapter($flysystem, $adapter, $config);
        });
    }
}
