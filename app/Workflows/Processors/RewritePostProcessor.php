<?php

namespace App\Workflows\Processors;

use App\AI\Contracts\SocialPostRewriter;
use App\Models\Article;
use App\Models\GeneratedPost;
use App\Models\WorkflowRun;
use App\Workflows\WorkflowStepProcessor;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class RewritePostProcessor implements WorkflowStepProcessor
{
    public function __construct(private readonly SocialPostRewriter $rewriter) {}

    public function process(Article $article, WorkflowRun $run): array
    {
        $facts = $run->steps()->where('step_key', 'extract_facts')->where('status', 'succeeded')->latest('attempt')->first()?->metadata['facts'] ?? null;
        $summary = $run->steps()->where('step_key', 'summarize')->where('status', 'succeeded')->latest('attempt')->first()?->metadata['result'] ?? null;
        if (! is_array($facts) || ! is_array($summary)) {
            throw new RuntimeException('Validated facts and summary are required before rewriting.');
        }
        $result = $this->rewriter->rewrite($article->title, $facts, $summary['summary'], $article->source->name, $article->source_url);
        if (! str_contains($result['body'], $article->source_url)) {
            $result['body'] = rtrim($result['body'])."\n\nที่มา: {$article->source->name} {$article->source_url}";
        }

        $post = DB::transaction(function () use ($article, $run, $result, $facts, $summary) {
            $existing = GeneratedPost::query()->where('workflow_run_id', $run->id)->lockForUpdate()->first();
            if ($existing) {
                $existing->update(['draft_text' => $result['title']."\n\n".$result['body'], 'metadata' => ['facts' => $facts, 'summary' => $summary, 'rewrite' => $result, 'placeholder' => false]]);

                return $existing;
            }
            $version = ((int) $article->generatedPosts()->max('version')) + 1;

            return $article->generatedPosts()->create([
                'workflow_run_id' => $run->id,
                'version' => $version,
                'status' => 'draft',
                'draft_text' => $result['title']."\n\n".$result['body'],
                'source_attribution' => $article->source->name,
                'source_url' => $article->source_url,
                'metadata' => ['facts' => $facts, 'summary' => $summary, 'rewrite' => $result, 'placeholder' => false],
            ]);
        });

        return ['generated_post_id' => $post->id, 'version' => $post->version, 'rewrite' => $result];
    }
}
