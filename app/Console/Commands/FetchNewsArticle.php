<?php

namespace App\Console\Commands;

use App\Models\Article;
use App\News\Exceptions\SourceFetchException;
use App\News\Services\ArticleFetchService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class FetchNewsArticle extends Command
{
    protected $signature = 'news:fetch {articleId : The internal NewsFlow article ID}';

    protected $description = 'Fetch and snapshot the essential text of one discovered article.';

    public function handle(ArticleFetchService $fetcher): int
    {
        $article = Article::find($this->argument('articleId'));
        if (! $article) {
            $this->error('Article was not found.');

            return self::FAILURE;
        }
        try {
            $article = $fetcher->fetch($article);
        } catch (Throwable $exception) {
            $category = $exception instanceof SourceFetchException ? $exception->category : 'unexpected';
            Log::warning('Article detail fetch failed.', [
                'article_id' => $article->id,
                'source_key' => $article->source->key,
                'error_category' => $category,
                'error_class' => $exception::class,
            ]);
            $this->error("Article fetch failed [{$category}]: ".$exception->getMessage());

            return self::FAILURE;
        }

        $this->info("Fetched article {$article->id}; status: {$article->status->value}; checksum: {$article->content_hash}");

        return self::SUCCESS;
    }
}
