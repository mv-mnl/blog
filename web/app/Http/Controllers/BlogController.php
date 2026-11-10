<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Models\Series;
use App\Models\Tag;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\MessageBag;

class BlogController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->query('q'));

        $posts = Post::published()
            ->with('tags', 'series')
            ->withCount('updates')
            ->search($q)
            ->latest('published_at')
            ->paginate(10)
            ->withQueryString();

        return view('blog.index', compact('posts', 'q'));
    }

    public function tag(Tag $tag)
    {
        $posts = $tag->posts()
            ->published()
            ->with('tags', 'series')
            ->withCount('updates')
            ->latest('published_at')
            ->paginate(10);

        return view('blog.index', ['posts' => $posts, 'tag' => $tag, 'q' => '']);
    }

    public function series(Series $series)
    {
        $posts = $series->posts()->with('tags')->get();
        abort_if($posts->isEmpty(), 404);

        return view('blog.series', compact('series', 'posts'));
    }

    public function show(Request $request, string $slug, ?MessageBag $commentErrors = null, array $old = [])
    {
        $post = Post::published()->with('tags', 'updates', 'series', 'approvedComments')->where('slug', $slug)->firstOrFail();
        $request->attributes->set('post_id', $post->id); // para las estadísticas

        $newer = Post::published()->where('published_at', '>', $post->published_at)->oldest('published_at')->first(['title', 'slug']);
        $older = Post::published()->where('published_at', '<', $post->published_at)->latest('published_at')->first(['title', 'slug']);

        $seriesPosts = $post->series ? $post->series->posts()->get(['id', 'title', 'slug', 'published_at']) : collect();

        // Relacionados: los que comparten más etiquetas; si no hay, los más recientes.
        $tagIds = $post->tags->pluck('id');
        $related = Post::published()
            ->whereKeyNot($post->id)
            ->when($seriesPosts->isNotEmpty(), fn ($q) => $q->whereNotIn('id', $seriesPosts->pluck('id')))
            ->withCount(['tags as shared_tags' => fn ($q) => $q->whereIn('tags.id', $tagIds)])
            ->orderByDesc('shared_tags')
            ->latest('published_at')
            ->limit(3)
            ->get(['id', 'title', 'slug', 'excerpt', 'body', 'cover_image', 'published_at']);

        [$html, $toc] = $post->rendered();
        foreach ($post->updates as $i => $update) {
            $toc[] = [2, 'actualizacion-'.($i + 1), 'Actualización: '.$update->title];
        }

        $status = $commentErrors ? 422 : 200;

        return response()->view('blog.show', [
            'post' => $post,
            'html' => $html,
            'toc' => count($toc) >= 3 ? $toc : [],
            'newer' => $newer,
            'older' => $older,
            'seriesPosts' => $seriesPosts,
            'related' => $related,
            'commentErrors' => $commentErrors ?? new MessageBag,
            'old' => $old,
            'commentToken' => Crypt::encryptString((string) time()),
            'commentSent' => $request->query('comentario') === 'enviado',
        ], $status);
    }

    public function feed()
    {
        $posts = Post::published()->latest('published_at')->limit(20)->get();

        return response()
            ->view('blog.feed', compact('posts'))
            ->header('Content-Type', 'application/rss+xml; charset=UTF-8');
    }
}
