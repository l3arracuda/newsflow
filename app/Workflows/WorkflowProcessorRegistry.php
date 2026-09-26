<?php

namespace App\Workflows;

use InvalidArgumentException;

class WorkflowProcessorRegistry
{
    /** @param array<string, WorkflowStepProcessor> $processors */
    public function __construct(private readonly array $processors) {}

    public function for(string $stepKey): WorkflowStepProcessor
    {
        return $this->processors[$stepKey] ?? throw new InvalidArgumentException("No processor registered for workflow step [{$stepKey}].");
    }
}
