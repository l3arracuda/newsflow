<?php

namespace App\Enums;

enum ReviewDecisionType: string
{
    case APPROVE = 'approve';
    case REJECT = 'reject';
    case REQUEST_CHANGES = 'request_changes';
}
