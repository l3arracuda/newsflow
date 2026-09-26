<?php

namespace App\Workflows\Processors;

use App\Models\Article;
use App\Models\WorkflowRun;
use App\News\Services\ArticleFetchService;
use App\Workflows\WorkflowStepProcessor;

class FetchDetailProcessor implements WorkflowStepProcessor
{
    public function __construct(private readonly ArticleFetchService $fetcher) {}

    public function process(Article $article, WorkflowRun $run): array
    {
        $snapshot = $article->snapshots()->latest('id')->first();
        if ($snapshot) {
            return ['snapshot_id' => $snapshot->id, 'checksum' => $snapshot->checksum, 'reused' => true];
        }
        $article = $this->fetcher->fetch($article);
        $snapshot = $article->snapshots()->latest('id')->firstOrFail();

        return ['snapshot_id' => $snapshot->id, 'checksum' => $snapshot->checksum, 'reused' => false];
    }
}
