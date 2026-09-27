<?php

namespace App\Images\Services;

use App\Images\Contracts\ImagePromptBuilder;
use App\Models\Article;
use App\Models\WorkflowRun;
use RuntimeException;

class EditorialImagePromptBuilder implements ImagePromptBuilder
{
    public const VERSION = 1;

    public function build(Article $article, WorkflowRun $run): array
    {
        $facts = $run->steps()->where('step_key', 'extract_facts')->where('status', 'succeeded')->latest('attempt')->first()?->metadata['facts'] ?? null;
        if (! is_array($facts)) {
            throw new RuntimeException('Extracted facts are required before building an image prompt.');
        }

        $category = (string) data_get($article->metadata, 'category', 'ข่าวทั่วไป');
        $sensitivity = (string) data_get($article->metadata, 'sensitivity', 'standard');
        $sensitive = in_array(mb_strtolower($sensitivity), ['sensitive', 'high', 'crime', 'accident', 'politics'], true)
            || preg_match('/อุบัติเหตุ|อาชญากรรม|ผู้เสียหาย|การเมือง|เสียชีวิต/u', $article->title);
        $event = trim((string) ($facts['event_action'] ?? $article->title));
        $place = implode(', ', array_slice(array_filter($facts['places'] ?? [], 'is_string'), 0, 3));
        $prompt = "Create a new editorial illustration about: {$event}. Category: {$category}.";
        if ($place !== '') {
            $prompt .= " Setting: {$place}.";
        }
        $prompt .= ' Use a respectful editorial illustration, clearly illustrative and not a depiction or photograph of the actual event. Do not include text, captions, publisher logos, watermarks, or identifiable real people.';
        $avoid = ['actual-event photographic claim', 'publisher/source logos', 'text or watermark', 'unprovided identifying details'];
        if ($sensitive) {
            $prompt .= ' Use a non-graphic, dignified symbolic scene; do not depict injury, bodies, victims, suspects, or identifiable faces.';
            $avoid = array_merge($avoid, ['graphic injury', 'victims or suspects', 'identifiable faces', 'sensational imagery']);
        }

        return [
            'prompt' => $prompt,
            'avoid' => $avoid,
            'aspect_ratio' => '1:1',
            'style' => 'editorial_illustration',
            'category' => $category,
            'sensitivity' => $sensitive ? 'high' : 'standard',
            'safety_notes' => ['generated illustration, not documentary evidence', 'no source image used as a reference'],
            'prompt_version' => self::VERSION,
            'generated_illustration' => true,
        ];
    }
}
