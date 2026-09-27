<?php

namespace App\Console\Commands;

use App\Models\Article;
use App\Workflows\WorkflowStarter;
use Illuminate\Console\Command;
use Throwable;

class ReprocessPlaceholderArticleWorkflow extends Command
{
    protected $signature = 'news:workflow:reprocess-placeholder {articleId : Internal NewsFlow article ID}';

    protected $description = 'Start a new workflow only when the latest successful output contains placeholders.';

    public function handle(WorkflowStarter $starter): int
    {
        $article = Article::find($this->argument('articleId'));
        if (! $article) {
            $this->error('Article was not found.');

            return self::FAILURE;
        }

        try {
            $run = $starter->reprocessPlaceholder($article);
        } catch (Throwable $exception) {
            $this->error('Workflow could not be reprocessed: '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->info("Queued new workflow run {$run->id}; previous versions and workflow history were preserved.");

        return self::SUCCESS;
    }
}
