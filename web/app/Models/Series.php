<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Solo lectura. */
class Series extends Model
{
    public function posts(): HasMany
    {
        return $this->hasMany(Post::class)->published()->oldest('published_at');
    }
}
