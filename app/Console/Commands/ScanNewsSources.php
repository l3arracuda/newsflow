<?php

namespace App\Console\Commands;

use App\Models\Source;
use App\Operations\SourceScanService;
use Illuminate\Console\Command;

class ScanNewsSources extends Command
{
    protected $signature = 'news:scan {sourceKey? : Scan one configured source; omit to scan every active source}';

    protected $description = 'Run an auditable news source scan for one or all active sources.';

    public function handle(SourceScanService $scans): int
    {
        $sourceKey = $this->argument('sourceKey');
        $sources = $sourceKey
            ? Source::query()->where('key', $sourceKey)->get()
            : Source::query()->where('is_active', true)->orderBy('id')->get();

        if ($sources->isEmpty()) {
            $this->error($sourceKey ? 'Configured source was not found.' : 'No active sources are configured.');

            return self::FAILURE;
        }

        $failed = false;
        foreach ($sources as $source) {
            $result = $scans->scan($source);
            if ($result['status'] === 'succeeded') {
                $this->info("{$source->key}: succeeded (created {$result['created']}, existing {$result['existing']})");
            } elseif ($result['status'] === 'failed') {
                $this->error("{$source->key}: failed [{$result['reason']}]");
                $failed = true;
            } else {
                $this->line("{$source->key}: skipped [{$result['reason']}]");
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
