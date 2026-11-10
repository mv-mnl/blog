<?php

namespace App\Models;

use App\Support\Html;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Post extends Model
{
    protected $fillable = ['title', 'slug', 'excerpt', 'body', 'cover_image', 'series_id', 'published_at', 'edited_at'];

    protected function casts(): array
    {
        return ['published_at' => 'datetime', 'edited_at' => 'datetime'];
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class)->orderBy('name');
    }

    public function updates(): HasMany
    {
        return $this->hasMany(PostUpdate::class)->oldest()->oldest('id');
    }

    public function series(): BelongsTo
    {
        return $this->belongsTo(Series::class);
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(PostRevision::class)->latest()->latest('id');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    /** Guarda en el historial cómo era el post antes de un cambio (máx. 50 versiones). */
    public function snapshot(array $original): void
    {
        $this->revisions()->create([
            'title' => $original['title'],
            'excerpt' => $original['excerpt'],
            'body' => $original['body'],
        ]);
        $keep = $this->revisions()->limit(50)->pluck('id');
        $this->revisions()->whereNotIn('id', $keep)->delete();
    }

    public function scopeSearch(Builder $query, ?string $term): void
    {
        $words = preg_split('/\s+/', preg_replace('/[^\p{L}\p{N}\s]/u', ' ', (string) $term), -1, PREG_SPLIT_NO_EMPTY);
        if (! $words) {
            return;
        }
        $boolean = collect($words)->map(fn ($w) => '+'.$w.'*')->implode(' ');
        $query->whereFullText(['title', 'excerpt', 'search_text'], $boolean, ['mode' => 'boolean']);
    }

    public function status(): string
    {
        return $this->published_at === null ? 'Borrador' : 'Publicado';
    }

    public function coverUrl(): ?string
    {
        return $this->cover_image ? '/uploads/'.$this->cover_image : null;
    }

    /** Recalcula el texto plano que usa el buscador (post + actualizaciones). */
    public function refreshSearchText(): void
    {
        $updates = $this->updates()->get()->map(fn ($u) => $u->title.' '.$u->body)->implode(' ');
        $this->forceFill(['search_text' => Html::text($this->body.' '.$updates)])->saveQuietly();
    }

    public static function uniqueSlug(string $source, ?int $ignoreId = null): string
    {
        $base = Str::limit(Str::slug($source) ?: 'post', 190, '');
        $slug = $base;
        $n = 2;
        while (static::where('slug', $slug)->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))->exists()) {
            $slug = "{$base}-".$n++;
        }

        return $slug;
    }
}
