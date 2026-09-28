<?php

namespace App\Publishing\Providers;

use App\Publishing\Contracts\SocialPublisher;
use App\Publishing\DTO\PublicationRequest;
use App\Publishing\DTO\PublishResult;
use App\Publishing\Exceptions\DefinitivePublishFailure;
use App\Publishing\Exceptions\UncertainPublishOutcome;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class MetaFacebookPublisher implements SocialPublisher
{
    public function publish(PublicationRequest $request): PublishResult
    {
        $version = trim((string) config('services.facebook.graph_version'));
        $pageId = trim((string) config('services.facebook.page_id'));
        $token = (string) config('services.facebook.page_access_token');

        if ($version === '' || ! preg_match('/^v\d+\.\d+$/', $version) || $pageId === '' || $token === '') {
            throw new DefinitivePublishFailure('Facebook publishing is not configured.');
        }

        try {
            $response = Http::baseUrl('https://graph.facebook.com/'.$version)
                ->withToken($token)
                ->timeout((int) config('services.facebook.timeout', 30))
                ->attach('source', $request->imageContents, $request->imageFileName, ['Content-Type' => $request->imageMimeType])
                ->post('/'.$pageId.'/photos', [
                    'caption' => $request->message."\n\nที่มา: ".$request->sourceUrl,
                    'published' => 'true',
                ]);
        } catch (ConnectionException) {
            throw new UncertainPublishOutcome('Meta connection ended before publication could be confirmed.');
        }

        if (! $response->successful()) {
            throw new DefinitivePublishFailure('Meta rejected the publication request (HTTP '.$response->status().').');
        }

        $postId = $response->json('post_id') ?: $response->json('id');
        if (! is_string($postId) || $postId === '') {
            throw new UncertainPublishOutcome('Meta accepted the request but returned no post identifier.');
        }

        return new PublishResult($postId, 'https://www.facebook.com/'.$postId);
    }
}
