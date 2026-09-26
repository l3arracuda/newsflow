<?php

namespace App\Providers;

use App\AI\Contracts\AiTextProvider;
use App\AI\Contracts\ArticleSummarizer;
use App\AI\Contracts\FactConsistencyChecker;
use App\AI\Contracts\FactExtractor;
use App\AI\Contracts\SocialPostRewriter;
use App\AI\FakeAiTextProvider;
use App\AI\OpenAiTextProvider;
use App\AI\Pipelines\AiTextPipeline;
use App\News\Adapters\SourceAdapterRegistry;
use App\News\Adapters\ThaiRath\ThaiRathAdapter;
use App\Workflows\Processors\CheckFactsProcessor;
use App\Workflows\Processors\DiscoveredArticleProcessor;
use App\Workflows\Processors\ExtractFactsProcessor;
use App\Workflows\Processors\FetchDetailProcessor;
use App\Workflows\Processors\PlaceholderProcessor;
use App\Workflows\Processors\RewritePostProcessor;
use App\Workflows\Processors\SummarizeArticleProcessor;
use App\Workflows\WorkflowProcessorRegistry;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(AiTextProvider::class, fn ($app) => config('services.ai_text.driver') === 'openai'
            ? $app->make(OpenAiTextProvider::class)
            : $app->make(FakeAiTextProvider::class));
        $this->app->singleton(AiTextPipeline::class);
        foreach ([FactExtractor::class, ArticleSummarizer::class, SocialPostRewriter::class, FactConsistencyChecker::class] as $contract) {
            $this->app->bind($contract, AiTextPipeline::class);
        }
        $this->app->singleton(SourceAdapterRegistry::class, function ($app) {
            return new SourceAdapterRegistry([
                'thairath' => $app->make(ThaiRathAdapter::class),
            ]);
        });
        $this->app->singleton(WorkflowProcessorRegistry::class, function ($app) {
            $processors = [
                'discover' => $app->make(DiscoveredArticleProcessor::class),
                'fetch_detail' => $app->make(FetchDetailProcessor::class),
                'extract_facts' => $app->make(ExtractFactsProcessor::class),
                'summarize' => $app->make(SummarizeArticleProcessor::class),
                'rewrite' => $app->make(RewritePostProcessor::class),
                'fact_check' => $app->make(CheckFactsProcessor::class),
            ];
            foreach (['image_prompt', 'image_generate', 'awaiting_review'] as $step) {
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
