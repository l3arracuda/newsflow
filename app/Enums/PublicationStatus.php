<?php

namespace App\Enums;

enum PublicationStatus: string
{
    case PENDING = 'pending';
    case PUBLISHING = 'publishing';
    case PUBLISHED = 'published';
    case FAILED = 'failed';
    case UNCERTAIN = 'uncertain';
}
