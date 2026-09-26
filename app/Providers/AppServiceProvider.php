<?php

namespace App\Providers;

use App\News\Adapters\SourceAdapterRegistry;
use App\News\Adapters\ThaiRath\ThaiRathAdapter;
use App\Workflows\Processors\DiscoveredArticleProcessor;
use App\Workflows\Processors\FetchDetailProcessor;
use App\Workflows\Processors\PlaceholderProcessor;
use App\Workflows\WorkflowProcessorRegistry;
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
        $this->app->singleton(WorkflowProcessorRegistry::class, function ($app) {
            $processors = [
                'discover' => $app->make(DiscoveredArticleProcessor::class),
                'fetch_detail' => $app->make(FetchDetailProcessor::class),
            ];
            foreach (['extract_facts', 'summarize', 'rewrite', 'fact_check', 'image_prompt', 'image_generate', 'awaiting_review'] as $step) {
                $processors[$step] = new PlaceholderProcessor($step);
            }

            return new WorkflowProcessorRegistry($processors);
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
