<?php

namespace App\Providers;

use App\News\Adapters\SourceAdapterRegistry;
use App\News\Adapters\ThaiRath\ThaiRathAdapter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(SourceAdapterRegistry::class, function ($app) {
            return new SourceAdapterRegistry([
                'thairath' => $app->make(ThaiRathAdapter::class),
            ]);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
