<?php

namespace App\Console\Commands;

use App\Models\Article;
use App\Workflows\WorkflowStarter;
use Illuminate\Console\Command;
use Throwable;

class StartArticleWorkflow extends Command
{
    protected $signature = 'news:workflow:start {articleId : Internal NewsFlow article ID}';

    protected $description = 'Queue the processing workflow for one article.';

    public function handle(WorkflowStarter $starter): int
    {
        $article = Article::find($this->argument('articleId'));
        if (! $article) {
            $this->error('Article was not found.');

            return self::FAILURE;
        }
        try {
            $result = $starter->start($article);
        } catch (Throwable $exception) {
            $this->error('Workflow could not be started: '.$exception->getMessage());

            return self::FAILURE;
        }
        $this->info(($result['dispatched'] ? 'Queued' : 'Already active or completed').' workflow run '.$result['run']->id.'.');

        return self::SUCCESS;
    }
}
