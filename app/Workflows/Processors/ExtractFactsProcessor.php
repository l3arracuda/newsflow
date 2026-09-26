<?php

namespace App\Workflows\Processors;

use App\AI\Contracts\FactExtractor;
use App\Models\Article;
use App\Models\WorkflowRun;
use App\Workflows\WorkflowStepProcessor;
use RuntimeException;

class ExtractFactsProcessor implements WorkflowStepProcessor
{
    public function __construct(private readonly FactExtractor $extractor) {}

    public function process(Article $article, WorkflowRun $run): array
    {
        $snapshot = $article->snapshots()->latest('id')->first();
        if (! $snapshot) {
            throw new RuntimeException('Cannot extract facts without an article snapshot.');
        }

        return ['snapshot_id' => $snapshot->id, 'facts' => $this->extractor->extract($article->title, $snapshot->normalized_excerpt)];
    }
}
