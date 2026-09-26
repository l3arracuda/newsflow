<?php

namespace App\Enums;

enum GeneratedPostStatus: string
{
    case DRAFT = 'draft';
    case READY_FOR_REVIEW = 'ready_for_review';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';
    case PUBLISHING = 'publishing';
    case PUBLISHED = 'published';
    case FAILED = 'failed';
}
