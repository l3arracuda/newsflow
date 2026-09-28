<?php

namespace App\Publishing\Providers;

use App\Publishing\Contracts\SocialPublisher;
use App\Publishing\DTO\PublicationRequest;
use App\Publishing\DTO\PublishResult;

class FakeFacebookPublisher implements SocialPublisher
{
    public function publish(PublicationRequest $request): PublishResult
    {
        $id = substr(hash('sha256', $request->idempotencyKey), 0, 20);

        return new PublishResult('fake_'.$id, 'https://facebook.test/posts/'.$id);
    }
}
