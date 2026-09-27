<?php

namespace App\Images\Contracts;

interface GeneratedAssetStorage
{
    public function put(string $path, string $contents): void;

    public function delete(string $path): void;
}
