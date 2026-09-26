<?php

namespace App\Workflows\Processors;

use App\Models\Article;
use App\Models\WorkflowRun;
use App\Workflows\WorkflowStepProcessor;

class DiscoveredArticleProcessor implements WorkflowStepProcessor
{
    public function process(Article $article, WorkflowRun $run): array
    {
        return ['article_id' => $article->id, 'source_id' => $article->source_id];
    }
}
