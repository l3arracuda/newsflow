<?php

namespace App\News\Adapters\ThaiRath;

use App\Models\Source;
use App\News\Adapters\NewsSourceAdapter;
use App\News\DTO\ArticleCandidate;
use App\News\DTO\ArticleDocument;
use App\News\Exceptions\SourceFetchException;
use App\News\Http\SourceHttpClient;

class ThaiRathAdapter implements NewsSourceAdapter
{
    public function __construct(private readonly SourceHttpClient $http, private readonly ThaiRathHtmlParser $parser, private readonly RobotsPolicy $robots) {}

    public function discover(Source $source): iterable
    {
        $this->assertAllowedUrl($source->listing_url);
        $this->robots->assertAllowed($source->listing_url, $source->key);

        return $this->parser->listing($this->http->get($source->listing_url, $source->key), $source->base_url);
    }

    public function fetchArticle(Source $source, ArticleCandidate $candidate): ArticleDocument
    {
        $this->assertAllowedUrl($candidate->url);
        $this->robots->assertAllowed($candidate->url, $source->key);

        return $this->parser->article($this->http->get($candidate->url, $source->key), $candidate->url);
    }

    private function assertAllowedUrl(string $url): void
    {
        $parts = parse_url($url);
        if (($parts['scheme'] ?? null) !== 'https' || ! in_array(strtolower($parts['host'] ?? ''), ['thairath.co.th', 'www.thairath.co.th'], true)) {
            throw new SourceFetchException('invalid_url', 'ThaiRath adapter only permits HTTPS URLs on thairath.co.th.');
        }
    }
}
