<?php

namespace App\News\Services;

use App\Enums\ArticleStatus;
use App\Models\Article;
use App\News\Adapters\SourceAdapterRegistry;
use App\News\DTO\ArticleCandidate;
use Illuminate\Support\Facades\DB;

class ArticleFetchService
{
    public function __construct(private readonly SourceAdapterRegistry $adapters) {}

    public function fetch(Article $article): Article
    {
        $source = $article->source;
        $candidate = new ArticleCandidate($article->title, $article->source_url, $article->source_published_at?->toImmutable(), $article->source_external_id);
        $document = $this->adapters->for($source)->fetchArticle($source, $candidate);
        $excerpt = trim($document->text);
        $checksum = hash('sha256', $excerpt);

        return DB::transaction(function () use ($article, $document, $excerpt, $checksum) {
            $article->snapshots()->firstOrCreate(['checksum' => $checksum], ['normalized_excerpt' => $excerpt, 'fetched_at' => now(), 'metadata' => $document->metadata]);
            $article->update(['title' => $document->title, 'canonical_url' => $document->url, 'source_published_at' => $document->publishedAt ?? $article->source_published_at, 'content_hash' => $checksum, 'status' => ArticleStatus::FETCHED]);

            return $article->refresh();
        });
    }
}
