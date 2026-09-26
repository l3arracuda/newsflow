<?php

namespace App\Enums;

enum WorkflowRunStatus: string
{
    case PENDING = 'pending';
    case RUNNING = 'running';
    case SUCCEEDED = 'succeeded';
    case FAILED = 'failed';
    case CANCELLED = 'cancelled';
}
