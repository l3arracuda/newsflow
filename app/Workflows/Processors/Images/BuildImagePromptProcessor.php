<?php

namespace App\Workflows\Processors\Images;

use App\Images\Contracts\ImagePromptBuilder;
use App\Models\Article;
use App\Models\WorkflowRun;
use App\Workflows\WorkflowStepProcessor;

class BuildImagePromptProcessor implements WorkflowStepProcessor
{
    public function __construct(private readonly ImagePromptBuilder $builder) {}

    public function process(Article $article, WorkflowRun $run): array
    {
        return ['prompt_result' => $this->builder->build($article, $run)];
    }
}
