<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Source extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'key', 'base_url', 'listing_url', 'adapter', 'is_active', 'last_scanned_at', 'config'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'last_scanned_at' => 'datetime', 'config' => 'array'];
    }

    public function articles(): HasMany
    {
        return $this->hasMany(Article::class);
    }

    public function latestArticle(): HasOne
    {
        return $this->hasOne(Article::class)->latestOfMany();
    }
}
