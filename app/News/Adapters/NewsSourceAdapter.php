<?php

namespace App\News\Adapters;

use App\Models\Source;
use App\News\DTO\ArticleCandidate;
use App\News\DTO\ArticleDocument;

interface NewsSourceAdapter
{
    /** @return iterable<ArticleCandidate> */
    public function discover(Source $source): iterable;

    public function fetchArticle(Source $source, ArticleCandidate $candidate): ArticleDocument;
}
