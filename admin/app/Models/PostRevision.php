<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PostRevision extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['title', 'excerpt', 'body'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }
}
