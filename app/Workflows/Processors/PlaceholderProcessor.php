<?php

namespace App\Workflows\Processors;

use App\Models\Article;
use App\Models\WorkflowRun;
use App\Workflows\WorkflowStepProcessor;

class PlaceholderProcessor implements WorkflowStepProcessor
{
    public function __construct(private readonly string $stepKey) {}

    public function process(Article $article, WorkflowRun $run): array
    {
        $metadata = ['placeholder' => true, 'processor' => 'not_configured'];
        if ($this->stepKey === 'extract_facts') {
            $metadata['snapshot_id'] = $article->snapshots()->latest('id')->value('id');
        }
        if ($this->stepKey === 'rewrite') {
            $post = $article->generatedPosts()->firstOrCreate(
                ['version' => 1],
                [
                    'status' => 'draft',
                    'draft_text' => '[NewsFlow Phase 04 placeholder — rewrite is not implemented.]',
                    'source_attribution' => $article->source->name,
                    'source_url' => $article->source_url,
                    'metadata' => ['placeholder' => true, 'workflow_run_id' => $run->id],
                ],
            );
            $metadata['generated_post_id'] = $post->id;
        }

        return $metadata;
    }
}
