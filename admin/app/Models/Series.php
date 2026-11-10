<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Series extends Model
{
    protected $fillable = ['title', 'slug'];

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }

    /** Busca la serie por nombre o la crea. Vacío = sin serie. */
    public static function idFromTitle(?string $title): ?int
    {
        $title = Str::limit(trim((string) $title), 150, '');
        if ($title === '') {
            return null;
        }

        return static::firstOrCreate(['slug' => Str::slug($title) ?: 'serie'], ['title' => $title])->id;
    }

    public static function deleteOrphans(): void
    {
        static::doesntHave('posts')->delete();
    }
}
