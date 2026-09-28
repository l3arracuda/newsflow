<?php

namespace App\News\Adapters\ThaiRath;

use App\News\DTO\ArticleCandidate;
use App\News\DTO\ArticleDocument;
use App\News\Exceptions\SourceFetchException;
use Carbon\CarbonImmutable;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Throwable;

class ThaiRathHtmlParser
{
    /** @return list<ArticleCandidate> */
    public function listing(string $html, string $baseUrl): array
    {
        [, $xpath] = $this->parse($html);
        $items = [];
        foreach ($xpath->query('//a[@href]') ?: [] as $link) {
            if (! $link instanceof DOMElement) {
                continue;
            }
            $url = $this->normalizeUrl($link->getAttribute('href'), $baseUrl);
            $title = $this->cleanText($link->textContent);
            if (! $url || ! $this->isArticleUrl($url) || mb_strlen($title) < 8) {
                continue;
            }
            $published = $xpath->query('ancestor::*[self::article or self::li][1]//time/@datetime', $link);
            $publishedAt = $this->date($published && $published->length ? $published->item(0)->nodeValue : null);
            $items[$url] = new ArticleCandidate($title, $url, $publishedAt, $this->externalId($url));
        }

        return array_values($items);
    }

    public function article(string $html, string $url): ArticleDocument
    {
        [, $xpath] = $this->parse($html);
        $title = $this->meta($xpath, ['og:title', 'twitter:title']) ?? $this->firstText($xpath, ['//article//h1', '//main//h1', '//h1']);
        if (! $title) {
            throw new SourceFetchException('parse_error', 'Could not extract article title.');
        }
        $text = $this->articleText($xpath);
        if (mb_strlen($text) < 80) {
            throw new SourceFetchException('parse_error', 'Could not extract enough article text.');
        }

        $maxChars = min(20000, max(1000, (int) config('newsflow.max_source_text_chars', 8000)));

        return new ArticleDocument($title, $url, mb_substr($text, 0, $maxChars), $this->date($this->meta($xpath, ['article:published_time', 'datePublished'])), ['extractor' => 'thairath_html_v1']);
    }

    private function parse(string $html): array
    {
        if (trim($html) === '') {
            throw new SourceFetchException('parse_error', 'Source returned an empty HTML document.');
        }
        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        try {
            $loaded = $document->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
        if (! $loaded) {
            throw new SourceFetchException('parse_error', 'Source HTML could not be parsed.');
        }

        return [$document, new DOMXPath($document)];
    }

    private function articleText(DOMXPath $xpath): string
    {
        foreach (['//article', '//main', '//*[@itemprop="articleBody"]'] as $selector) {
            $nodes = $xpath->query($selector);
            if (! $nodes || ! $nodes->length) {
                continue;
            }
            $paragraphs = [];
            foreach ($xpath->query('.//p', $nodes->item(0)) ?: [] as $paragraph) {
                $value = $this->cleanText($paragraph->textContent);
                if (mb_strlen($value) >= 30) {
                    $paragraphs[] = $value;
                }
            }
            $text = implode("\n\n", array_unique($paragraphs));
            if (mb_strlen($text) >= 80) {
                return $text;
            }
        }

        return '';
    }

    private function meta(DOMXPath $xpath, array $names): ?string
    {
        foreach ($names as $name) {
            $nodes = $xpath->query('//meta[@property="'.$name.'" or @name="'.$name.'"]/@content');
            if ($nodes && $nodes->length && ($value = trim($nodes->item(0)->nodeValue ?? '')) !== '') {
                return $value;
            }
        }

        return null;
    }

    private function firstText(DOMXPath $xpath, array $selectors): ?string
    {
        foreach ($selectors as $selector) {
            $nodes = $xpath->query($selector);
            if ($nodes && $nodes->length && ($value = $this->cleanText($nodes->item(0)->textContent)) !== '') {
                return $value;
            }
        }

        return null;
    }

    private function normalizeUrl(string $href, string $baseUrl): ?string
    {
        $href = trim(html_entity_decode($href, ENT_QUOTES | ENT_HTML5));
        if ($href === '' || str_starts_with($href, '#') || preg_match('/^(javascript|mailto|tel):/i', $href)) {
            return null;
        }
        if (str_starts_with($href, '//')) {
            $href = 'https:'.$href;
        } elseif (str_starts_with($href, '/')) {
            $href = rtrim($baseUrl, '/').$href;
        } elseif (! preg_match('/^https?:\/\//i', $href)) {
            return null;
        }
        $parts = parse_url($href);
        if (! $parts || ! isset($parts['host']) || ! in_array(strtolower($parts['host']), ['thairath.co.th', 'www.thairath.co.th'], true)) {
            return null;
        }
        $path = rtrim(preg_replace('#/+#', '/', $parts['path'] ?? '/') ?: '/', '/') ?: '/';

        return 'https://www.thairath.co.th'.$path;
    }

    private function isArticleUrl(string $url): bool
    {
        return (bool) preg_match('#^https://www\.thairath\.co\.th/news/[a-z0-9_-]+/.+$#i', $url);
    }

    private function externalId(string $url): ?string
    {
        $last = basename(parse_url($url, PHP_URL_PATH) ?: '');

        return preg_match('/^[a-z0-9_-]{3,191}$/i', $last) ? $last : null;
    }

    private function cleanText(string $text): string
    {
        return trim(preg_replace('/\s+/u', ' ', html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?? '');
    }

    private function date(?string $value): ?CarbonImmutable
    {
        if (! $value) {
            return null;
        }
        try {
            return CarbonImmutable::parse($value);
        } catch (Throwable) {
            return null;
        }
    }
}
