<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Actualización de un post: un mini-post (título, portada, contenido) en su línea de tiempo. */
class PostUpdate extends Model
{
    protected $fillable = ['title', 'body', 'cover_image'];

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    public function coverUrl(): ?string
    {
        return $this->cover_image ? '/uploads/'.$this->cover_image : null;
    }
}
