<?php

namespace App\Enums;

enum ArticleStatus: string
{
    case DISCOVERED = 'discovered';
    case FETCHED = 'fetched';
    case PROCESSING = 'processing';
    case READY_FOR_REVIEW = 'ready_for_review';
    case FLAGGED = 'flagged';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';
    case PUBLISHING = 'publishing';
    case PUBLISHED = 'published';
    case FAILED = 'failed';
}
