<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Support\Html;
use Illuminate\Support\Str;

/** Solo lectura: el esquema y los datos los gestiona la app admin. */
class Post extends Model
{
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

    public function approvedComments(): HasMany
    {
        return $this->hasMany(Comment::class)->where('status', 'approved')->oldest()->oldest('id');
    }

    /** [html, índice] listo para mostrar (ver Html::render). */
    public function rendered(): array
    {
        return $this->rendered ??= Html::render($this->body);
    }

    private ?array $rendered = null;

    public function scopePublished(Builder $query): void
    {
        $query->whereNotNull('published_at')->where('published_at', '<=', now());
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


    public function readingMinutes(): int
    {
        return max(1, (int) ceil(count(preg_split('/\s+/u', Html::text($this->body), -1, PREG_SPLIT_NO_EMPTY)) / 220));
    }

    public function coverUrl(): ?string
    {
        return $this->cover_image ? '/uploads/'.$this->cover_image : null;
    }

    public function summary(): string
    {
        return $this->excerpt ?: Str::limit(Html::text($this->body), 200);
    }
}
