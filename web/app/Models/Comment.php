<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** La web solo puede leer comentarios aprobados y crear nuevos (quedan pendientes). */
class Comment extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['post_id', 'name', 'body', 'ip_hash'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }
}
