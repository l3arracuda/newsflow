<?php

namespace App\Console\Commands;

use App\Images\Services\EditorialImagePromptBuilder;
use App\Images\Services\GeneratedImageAssetService;
use App\Models\GeneratedPost;
use App\Models\WorkflowRun;
use Illuminate\Console\Command;
use RuntimeException;

class RegenerateImage extends Command
{
    protected $signature = 'news:image:regenerate {generatedPostId : Generated post ID}';

    protected $description = 'Regenerate an editorial illustration while preserving existing asset versions';

    public function handle(GeneratedImageAssetService $assets, EditorialImagePromptBuilder $prompts): int
    {
        $post = GeneratedPost::query()->find($this->argument('generatedPostId'));
        if (! $post) {
            $this->error('Generated post not found.');

            return self::FAILURE;
        }
        $run = WorkflowRun::query()->where('article_id', $post->article_id)->where('status', 'succeeded')->latest('id')->first();
        if (! $run) {
            $this->error('No completed workflow is available to rebuild the image prompt.');

            return self::FAILURE;
        }
        try {
            $prompt = $prompts->build($post->article, $run);
            $asset = $assets->generate($post, $prompt);
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
        $this->info("Generated asset #{$asset->id}, version {$asset->version}.");

        return self::SUCCESS;
    }
}
