<?php

namespace App\News\Adapters;

use App\Models\Source;
use InvalidArgumentException;

class SourceAdapterRegistry
{
    /** @param array<string, NewsSourceAdapter> $adapters */
    public function __construct(private readonly array $adapters) {}

    public function for(Source $source): NewsSourceAdapter
    {
        return $this->adapters[$source->adapter] ?? throw new InvalidArgumentException("No source adapter registered for [{$source->adapter}].");
    }
}
