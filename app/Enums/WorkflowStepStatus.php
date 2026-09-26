<?php

namespace App\Enums;

enum WorkflowStepStatus: string
{
    case PENDING = 'pending';
    case RUNNING = 'running';
    case SUCCEEDED = 'succeeded';
    case FAILED = 'failed';
    case SKIPPED = 'skipped';
}
