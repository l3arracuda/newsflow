<?php

namespace App\Publishing\Contracts;

use App\Publishing\DTO\PublicationRequest;
use App\Publishing\DTO\PublishResult;

interface SocialPublisher
{
    public function publish(PublicationRequest $request): PublishResult;
}
