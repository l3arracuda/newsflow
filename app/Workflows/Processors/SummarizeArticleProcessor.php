<?php

namespace App\Workflows\Processors;

use App\AI\Contracts\ArticleSummarizer;
use App\Models\Article;
use App\Models\WorkflowRun;
use App\Workflows\WorkflowStepProcessor;
use RuntimeException;

class SummarizeArticleProcessor implements WorkflowStepProcessor
{
    public function __construct(private readonly ArticleSummarizer $summarizer) {}

    public function process(Article $article, WorkflowRun $run): array
    {
        $facts = $run->steps()->where('step_key', 'extract_facts')->where('status', 'succeeded')->latest('attempt')->first()?->metadata['facts'] ?? null;
        if (! is_array($facts)) {
            throw new RuntimeException('Validated extracted facts are required before summarizing.');
        }

        return ['result' => $this->summarizer->summarize($facts)];
    }
}
