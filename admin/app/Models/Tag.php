<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

class Tag extends Model
{
    protected $fillable = ['name', 'slug'];

    public function posts(): BelongsToMany
    {
        return $this->belongsToMany(Post::class);
    }

    /**
     * Convierte "laravel, Docker ,php" en IDs de etiquetas, creando las que falten.
     */
    public static function idsFromString(?string $input): array
    {
        return collect(explode(',', (string) $input))
            ->map(fn ($name) => Str::limit(trim($name), 60, ''))
            ->filter()
            ->unique(fn ($name) => Str::slug($name))
            ->map(fn ($name) => static::firstOrCreate(['slug' => Str::slug($name)], ['name' => $name])->id)
            ->values()
            ->all();
    }

    public static function deleteOrphans(): void
    {
        static::doesntHave('posts')->delete();
    }
}
