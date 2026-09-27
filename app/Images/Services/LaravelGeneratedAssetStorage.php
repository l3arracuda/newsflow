<?php

namespace App\Images\Services;

use App\Images\Contracts\GeneratedAssetStorage;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class LaravelGeneratedAssetStorage implements GeneratedAssetStorage
{
    public function put(string $path, string $contents): void
    {
        $disk = config('services.image_generation.disk', 'local');
        if (! Storage::disk($disk)->put($path, $contents)) {
            throw new RuntimeException('Generated image could not be saved to storage.');
        }
    }

    public function delete(string $path): void
    {
        Storage::disk(config('services.image_generation.disk', 'local'))->delete($path);
    }
}
