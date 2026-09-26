<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Source extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'key', 'base_url', 'listing_url', 'adapter', 'is_active', 'config'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'config' => 'array'];
    }

    public function articles(): HasMany
    {
        return $this->hasMany(Article::class);
    }
}
