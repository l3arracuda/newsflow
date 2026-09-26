<?php

namespace App\News\Services;

use App\Enums\ArticleStatus;
use App\Models\Article;
use App\Models\Source;
use App\News\Adapters\SourceAdapterRegistry;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ArticleDiscoveryService
{
    public function __construct(private readonly SourceAdapterRegistry $adapters) {}

    public function discover(Source $source, bool $dryRun = false): array
    {
        $result = ['candidates' => 0, 'created' => 0, 'existing' => 0, 'items' => []];
        foreach ($this->adapters->for($source)->discover($source) as $candidate) {
            $result['candidates']++;
            $lookup = fn () => Article::where('source_id', $source->id)->where(fn ($q) => $q->where('source_url', $candidate->url)->when($candidate->externalId, fn ($q, $id) => $q->orWhere('source_external_id', $id)))->first();
            $article = $lookup();
            if ($dryRun || $article) {
                if ($article) {
                    $result['existing']++;
                }
                $result['items'][] = ['title' => $candidate->title, 'url' => $candidate->url, 'exists' => (bool) $article];

                continue;
            }
            try {
                $article = DB::transaction(fn () => Article::create(['source_id' => $source->id, 'source_external_id' => $candidate->externalId, 'source_url' => $candidate->url, 'canonical_url' => $candidate->url, 'title' => $candidate->title, 'source_published_at' => $candidate->publishedAt, 'discovered_at' => now(), 'status' => ArticleStatus::DISCOVERED]));
                $result['created']++;
            } catch (QueryException $exception) {
                $article = $lookup();
                if (! $article) {
                    Log::warning('Source candidate insert failed.', ['source_key' => $source->key, 'error_class' => $exception::class]);
                    throw $exception;
                }
                $result['existing']++;
            }
            $result['items'][] = ['title' => $article->title, 'url' => $article->source_url, 'exists' => $article->wasRecentlyCreated === false];
        }

        return $result;
    }
}
