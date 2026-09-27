<?php

namespace App\Workflows\Processors;

use App\AI\Contracts\FactExtractor;
use App\AI\FactEvidenceValidator;
use App\Models\Article;
use App\Models\WorkflowRun;
use App\Workflows\WorkflowStepProcessor;
use RuntimeException;

class ExtractFactsProcessor implements WorkflowStepProcessor
{
    public function __construct(private readonly FactExtractor $extractor, private readonly FactEvidenceValidator $evidence) {}

    public function process(Article $article, WorkflowRun $run): array
    {
        $snapshot = $article->snapshots()->latest('id')->first();
        if (! $snapshot) {
            throw new RuntimeException('Cannot extract facts without an article snapshot.');
        }

        $facts = $this->extractor->extract($article->title, $snapshot->normalized_excerpt);

        return [
            'snapshot_id' => $snapshot->id,
            'facts' => $this->evidence->validate($facts, $snapshot->normalized_excerpt),
        ];
    }
}
