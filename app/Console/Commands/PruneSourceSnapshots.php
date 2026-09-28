<?php

namespace App\Console\Commands;

use App\Models\Article;
use App\Models\ArticleSnapshot;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PruneSourceSnapshots extends Command
{
    protected $signature = 'newsflow:retention:prune-snapshots {--execute : Permanently remove eligible old source snapshots (default is dry run)}';

    protected $description = 'Preview or prune source snapshots older than the configured retention period.';

    public function handle(): int
    {
        $days = max(1, (int) config('newsflow.snapshot_retention_days', 365));
        $cutoff = now()->subDays($days);
        $query = ArticleSnapshot::query()->where('fetched_at', '<', $cutoff);
        $count = $query->count();

        if (! $this->option('execute')) {
            $this->info("Dry run: {$count} source snapshots older than {$days} days are eligible; nothing was deleted.");

            return self::SUCCESS;
        }

        $idsByArticle = (clone $query)->get(['id', 'article_id'])->groupBy('article_id');
        $deleted = 0;
        DB::transaction(function () use ($idsByArticle, $cutoff, &$deleted) {
            foreach ($idsByArticle as $articleId => $snapshots) {
                $article = Article::query()->lockForUpdate()->find($articleId);
                if (! $article) {
                    continue;
                }
                $ids = $snapshots->pluck('id');
                $removed = $article->snapshots()->whereIn('id', $ids)->where('fetched_at', '<', $cutoff)->delete();
                if ($removed > 0) {
                    $deleted += $removed;
                    $article->auditLogs()->create([
                        'actor_type' => 'system', 'event' => 'source_snapshots.retention_pruned',
                        'metadata' => ['deleted_count' => $removed, 'retention_days' => (int) config('newsflow.snapshot_retention_days', 365), 'cutoff' => $cutoff->toIso8601String()],
                    ]);
                }
            }
        });

        $this->info("Source snapshots pruned: {$deleted}.");

        return self::SUCCESS;
    }
}
