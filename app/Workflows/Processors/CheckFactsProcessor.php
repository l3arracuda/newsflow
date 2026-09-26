<?php

namespace App\Workflows\Processors;

use App\AI\Contracts\FactConsistencyChecker;
use App\Models\Article;
use App\Models\WorkflowRun;
use App\Workflows\WorkflowStepProcessor;
use RuntimeException;

class CheckFactsProcessor implements WorkflowStepProcessor
{
    public function __construct(private readonly FactConsistencyChecker $checker) {}

    public function process(Article $article, WorkflowRun $run): array
    {
        $facts = $run->steps()->where('step_key', 'extract_facts')->where('status', 'succeeded')->latest('attempt')->first()?->metadata['facts'] ?? null;
        $rewrite = $run->steps()->where('step_key', 'rewrite')->where('status', 'succeeded')->latest('attempt')->first()?->metadata['rewrite'] ?? null;
        if (! is_array($facts) || ! is_array($rewrite)) {
            throw new RuntimeException('Facts and generated draft are required for fact consistency check.');
        }
        $result = $this->checker->check($facts, $rewrite);
        $result['flagged'] = ! $result['pass'];

        return $result;
    }
}
