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
use App\Images\Contracts\GeneratedAssetStorage;
use App\Images\Contracts\ImageGenerationProvider;
use App\Images\Contracts\ImagePromptBuilder;
use App\Images\Providers\FakeImageGenerationProvider;
use App\Images\Providers\OpenAiImageGenerationProvider;
use App\Images\Services\EditorialImagePromptBuilder;
use App\Images\Services\LaravelGeneratedAssetStorage;
use App\News\Adapters\SourceAdapterRegistry;
use App\News\Adapters\ThaiRath\ThaiRathAdapter;
use App\Publishing\Contracts\SocialPublisher;
use App\Publishing\Providers\FakeFacebookPublisher;
use App\Publishing\Providers\MetaFacebookPublisher;
use App\Workflows\Processors\CheckFactsProcessor;
use App\Workflows\Processors\DiscoveredArticleProcessor;
use App\Workflows\Processors\ExtractFactsProcessor;
use App\Workflows\Processors\FetchDetailProcessor;
use App\Workflows\Processors\Images\BuildImagePromptProcessor;
use App\Workflows\Processors\Images\GenerateImageProcessor;
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
        $this->app->bind(SocialPublisher::class, fn ($app) => config('services.facebook.driver') === 'meta'
            ? $app->make(MetaFacebookPublisher::class)
            : $app->make(FakeFacebookPublisher::class));
        $this->app->bind(AiTextProvider::class, fn ($app) => config('services.ai_text.driver') === 'openai'
            ? $app->make(OpenAiTextProvider::class)
            : $app->make(FakeAiTextProvider::class));
        $this->app->singleton(AiTextPipeline::class);
        $this->app->bind(ImagePromptBuilder::class, EditorialImagePromptBuilder::class);
        $this->app->bind(GeneratedAssetStorage::class, LaravelGeneratedAssetStorage::class);
        $this->app->bind(ImageGenerationProvider::class, fn ($app) => config('services.image_generation.driver') === 'openai'
            ? $app->make(OpenAiImageGenerationProvider::class)
            : $app->make(FakeImageGenerationProvider::class));
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
                'image_prompt' => $app->make(BuildImagePromptProcessor::class),
                'image_generate' => $app->make(GenerateImageProcessor::class),
            ];
            foreach (['awaiting_review'] as $step) {
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
