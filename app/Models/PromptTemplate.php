<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PromptTemplate extends Model
{
    use HasFactory;

    protected $fillable = ['key', 'name', 'version', 'template', 'variables', 'is_active', 'metadata'];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'variables' => 'array',
            'is_active' => 'boolean',
            'metadata' => 'array',
        ];
    }
}
