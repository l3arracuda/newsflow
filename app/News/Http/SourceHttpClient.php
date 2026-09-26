<?php

namespace App\News\Http;

use App\News\Exceptions\SourceFetchException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;

class SourceHttpClient
{
    public function get(string $url, string $sourceKey, bool $allowNotFound = false): string
    {
        for ($attempt = 1; $attempt <= 3; $attempt++) {
            $key = 'source-fetch:'.$sourceKey;
            $delay = RateLimiter::availableIn($key);
            if ($delay > 0) {
                usleep(($delay * 1_000_000) + 10_000);
            }
            RateLimiter::hit($key, 1);
            try {
                $response = Http::accept('text/html,application/xhtml+xml')
                    ->withUserAgent('NewsFlow/1.0 (+https://github.com/l3arracuda/newsflow; editorial news workflow)')
                    ->connectTimeout(5)->timeout(15)->get($url);
            } catch (ConnectionException $exception) {
                if ($attempt < 3) {
                    usleep(100_000 * $attempt);

                    continue;
                }
                throw new SourceFetchException('timeout', 'Source request timed out after three attempts.', $exception);
            }
            if ($response->status() === 429) {
                throw new SourceFetchException('rate_limited', 'Source returned HTTP 429.');
            }
            if ($allowNotFound && $response->status() === 404) {
                return '';
            }
            if ($response->serverError()) {
                if ($attempt < 3) {
                    usleep(100_000 * $attempt);

                    continue;
                }
                throw new SourceFetchException('upstream_error', 'Source returned HTTP '.$response->status().' after three attempts.');
            }
            if (! $response->successful()) {
                throw new SourceFetchException('http_error', 'Source returned HTTP '.$response->status().'.');
            }

            return $response->body();
        }
        throw new SourceFetchException('upstream_error', 'Source request failed after three attempts.');
    }
}
