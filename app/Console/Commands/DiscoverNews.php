<?php

namespace App\Console\Commands;

use App\Models\Source;
use App\News\Exceptions\SourceFetchException;
use App\News\Services\ArticleDiscoveryService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class DiscoverNews extends Command
{
    protected $signature = 'news:discover {sourceKey} {--dry-run : Fetch and parse candidates without database writes}';

    protected $description = 'Discover article candidates from a configured news source.';

    public function handle(ArticleDiscoveryService $discovery): int
    {
        $source = Source::where('key', $this->argument('sourceKey'))->first();
        if (! $source) {
            $this->error('Configured source was not found.');

            return self::FAILURE;
        }
        if (! $source->is_active) {
            $this->error('Configured source is inactive.');

            return self::FAILURE;
        }
        try {
            $result = $discovery->discover($source, (bool) $this->option('dry-run'));
        } catch (Throwable $exception) {
            $category = $exception instanceof SourceFetchException ? $exception->category : 'unexpected';
            Log::warning('News source discovery failed.', [
                'source_key' => $source->key,
                'error_category' => $category,
                'error_class' => $exception::class,
            ]);
            $this->error("Discovery failed [{$category}]: ".$exception->getMessage());

            return self::FAILURE;
        }
        foreach ($result['items'] as $item) {
            $this->line(($item['exists'] ? '[exists] ' : '[new] ').$item['title'].' — '.$item['url']);
        }
        $this->info("Candidates: {$result['candidates']}; created: {$result['created']}; existing: {$result['existing']}".($this->option('dry-run') ? ' (dry run)' : ''));

        return self::SUCCESS;
    }
}
