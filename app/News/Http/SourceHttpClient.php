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
        $this->assertSafeUrl($url);
        for ($attempt = 1; $attempt <= 3; $attempt++) {
            $key = 'source-fetch:'.$sourceKey;
            $limit = max(1, (int) config('newsflow.source_requests_per_minute', 30));
            if (RateLimiter::tooManyAttempts($key, $limit)) {
                throw new SourceFetchException('rate_limited', 'Source request rate limit exceeded.');
            }
            RateLimiter::hit($key, 60);
            try {
                $response = Http::accept('text/html,application/xhtml+xml')
                    ->withUserAgent('NewsFlow/1.0 (+https://github.com/l3arracuda/newsflow; editorial news workflow)')
                    ->withOptions(['allow_redirects' => false, 'stream' => true])
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
            if ($response->status() >= 300 && $response->status() < 400) {
                throw new SourceFetchException('unsafe_redirect', 'Source redirects are not followed.');
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

            $maxBytes = max(1024, (int) config('newsflow.max_source_bytes', 5 * 1024 * 1024));
            $contentLength = (int) $response->header('Content-Length', 0);
            if ($contentLength > $maxBytes) {
                throw new SourceFetchException('response_too_large', 'Source response exceeded the configured size limit.');
            }
            $contentType = strtolower(trim(explode(';', (string) $response->header('Content-Type', 'text/plain'))[0]));
            if (! in_array($contentType, ['text/html', 'application/xhtml+xml', 'text/plain'], true)) {
                throw new SourceFetchException('invalid_content_type', 'Source returned an unsupported content type.');
            }

            $stream = $response->toPsrResponse()->getBody();
            $body = '';
            while (! $stream->eof()) {
                $body .= $stream->read(min(8192, $maxBytes + 1 - strlen($body)));
                if (strlen($body) > $maxBytes) {
                    throw new SourceFetchException('response_too_large', 'Source response exceeded the configured size limit.');
                }
            }

            return $body;
        }
        throw new SourceFetchException('upstream_error', 'Source request failed after three attempts.');
    }

    private function assertSafeUrl(string $url): void
    {
        $parts = parse_url($url);
        if (! is_array($parts)) {
            throw new SourceFetchException('invalid_url', 'Malformed source URL.');
        }
        $host = strtolower((string) ($parts['host'] ?? ''));
        $allowedHosts = ['thairath.co.th', 'www.thairath.co.th'];
        $portAllowed = ! isset($parts['port']) || $parts['port'] === 443;
        $hasCredentials = isset($parts['user']) || isset($parts['pass']);
        if (($parts['scheme'] ?? null) !== 'https' || ! in_array($host, $allowedHosts, true) || ! $portAllowed || $hasCredentials) {
            throw new SourceFetchException('invalid_url', 'Only allowlisted HTTPS source URLs are permitted.');
        }

        if (filter_var($host, FILTER_VALIDATE_IP) && ! filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            throw new SourceFetchException('private_address', 'Private or reserved network addresses are not permitted.');
        }
    }
}
