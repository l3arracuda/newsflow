<?php

namespace App\Images\Contracts;

use App\Models\Article;
use App\Models\WorkflowRun;

interface ImagePromptBuilder
{
    public function build(Article $article, WorkflowRun $run): array;
}
