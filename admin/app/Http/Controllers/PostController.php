<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Models\PostRevision;
use App\Models\Series;
use App\Models\Tag;
use App\Support\Html;
use App\Support\Images;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PostController extends Controller
{
    private const IMAGE_RULES = ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:5120'];

    public function index(Request $request)
    {
        $posts = Post::query()
            ->with('tags')
            ->withCount('updates')
            ->search($request->query('q'))
            ->latest('updated_at')
            ->paginate(20)
            ->withQueryString();

        return view('posts.index', ['posts' => $posts, 'q' => $request->query('q')]);
    }

    public function create()
    {
        return view('posts.form', ['post' => new Post, 'allSeries' => Series::orderBy('title')->pluck('title')]);
    }

    public function store(Request $request)
    {
        $post = new Post;
        $message = $this->save($request, $post);

        return redirect()->route('posts.edit', $post)->with('status', $message);
    }

    public function edit(Post $post)
    {
        return view('posts.form', [
            'post' => $post->load('tags', 'updates', 'series', 'revisions'),
            'allSeries' => Series::orderBy('title')->pluck('title'),
        ]);
    }

    public function update(Request $request, Post $post)
    {
        $message = $this->save($request, $post);

        return redirect()->route('posts.edit', $post)->with('status', $message);
    }

    public function destroy(Post $post)
    {
        $files = $post->updates()->pluck('cover_image')->push($post->cover_image)->filter();

        DB::transaction(function () use ($post) {
            $post->delete();
            Tag::deleteOrphans();
            Series::deleteOrphans();
        });
        Storage::disk('uploads')->delete($files->all());

        return redirect()->route('posts.index')->with('status', "«{$post->title}» eliminado.");
    }

    /** Sube una imagen para insertarla dentro del contenido (editor visual). */
    public function upload(Request $request)
    {
        $request->validate(['image' => ['required', ...array_slice(self::IMAGE_RULES, 1)]]);
        $path = Images::store($request->file('image'));

        return response()->json(['url' => '/uploads/'.$path]);
    }

    /** Vista previa de una versión antigua (para el panel de historial). */
    public function revision(Post $post, PostRevision $revision)
    {
        abort_unless($revision->post_id === $post->id, 404);

        return response()->json([
            'title' => $revision->title,
            'excerpt' => $revision->excerpt,
            'html' => Html::clean($revision->body),
            'date' => $revision->created_at->translatedFormat('j \\d\\e F \\d\\e Y, H:i'),
        ]);
    }

    /** Restaura una versión antigua. La versión actual pasa al historial, así que se puede deshacer. */
    public function restore(Post $post, PostRevision $revision)
    {
        abort_unless($revision->post_id === $post->id, 404);

        DB::transaction(function () use ($post, $revision) {
            $post->snapshot($post->only(['title', 'excerpt', 'body']));
            $post->fill($revision->only(['title', 'excerpt', 'body']));
            if ($post->published_at) {
                $post->edited_at = now();
            }
            $post->save();
            $post->refreshSearchText();
        });

        return redirect()->route('posts.edit', $post)
            ->with('status', 'Versión del '.$revision->created_at->translatedFormat('j M Y, H:i').' restaurada. La anterior quedó en el historial.');
    }

    /** Guarda el post y sus actualizaciones en una sola operación. Devuelve el mensaje para el usuario. */
    private function save(Request $request, Post $post): string
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'slug' => ['nullable', 'string', 'max:190', 'alpha_dash:ascii'],
            'excerpt' => ['nullable', 'string', 'max:300'],
            'body' => ['nullable', 'string'],
            'tags' => ['nullable', 'string', 'max:500'],
            'series' => ['nullable', 'string', 'max:150'],
            'status' => ['required', Rule::in(['draft', 'published'])],
            'cover' => self::IMAGE_RULES,
            'remove_cover' => ['nullable', 'boolean'],
            'updates' => ['nullable', 'array', 'max:200'],
            'updates.*.id' => ['nullable', 'integer'],
            'updates.*.title' => ['nullable', 'string', 'max:200'],
            'updates.*.body' => ['nullable', 'string'],
            'updates.*.cover' => self::IMAGE_RULES,
            'updates.*.remove_cover' => ['nullable', 'boolean'],
            'updates.*.delete' => ['nullable', 'boolean'],
        ]);

        // --- Validar contenido (el editor vacío envía "<p></p>") ---
        $errors = [];
        $body = Html::clean($data['body'] ?? '');
        if (Html::isEmpty($body)) {
            $errors['body'] = 'Escribe algo en el post antes de guardarlo.';
        }

        $existingUpdates = $post->exists ? $post->updates()->get()->keyBy('id') : collect();
        $rows = [];
        foreach ($data['updates'] ?? [] as $key => $row) {
            $model = isset($row['id']) ? $existingUpdates->get($row['id']) : null;
            if (isset($row['id']) && ! $model) {
                continue; // id que no pertenece a este post
            }
            if (! empty($row['delete'])) {
                if ($model) {
                    $rows[] = ['delete' => true, 'model' => $model];
                }

                continue;
            }

            $title = trim($row['title'] ?? '');
            $ubody = Html::clean($row['body'] ?? '');
            $file = $request->file("updates.$key.cover");

            if (! $model && $title === '' && Html::isEmpty($ubody) && ! $file) {
                continue; // tarjeta nueva que se dejó vacía
            }
            if ($title === '') {
                $errors["updates.$key.title"] = 'Cada actualización necesita un título.';
            }
            if (Html::isEmpty($ubody)) {
                $errors["updates.$key.body"] = 'Cada actualización necesita contenido.';
            }
            $rows[] = [
                'delete' => false,
                'model' => $model,
                'title' => $title,
                'body' => $ubody,
                'file' => $file,
                'remove_cover' => ! empty($row['remove_cover']),
            ];
        }

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        // --- Guardar ---
        $wasPublished = $post->published_at !== null;
        $oldFingerprint = Html::fingerprint($post->body);
        $original = $post->only(['title', 'excerpt', 'body']);
        $filesToDelete = [];

        $post->fill([
            'title' => $data['title'],
            'slug' => Post::uniqueSlug(($data['slug'] ?? null) ?: $data['title'], $post->id),
            'excerpt' => ($data['excerpt'] ?? null) ?: null,
            'body' => $body,
            // Fecha automática: se fija la primera vez que se publica y se conserva después.
            'published_at' => $data['status'] === 'published' ? ($post->published_at ?? now()) : null,
            'series_id' => Series::idFromTitle($data['series'] ?? null),
        ]);

        if ($file = $request->file('cover')) {
            $filesToDelete[] = $post->cover_image;
            $post->cover_image = Images::store($file, 2000);
        } elseif ($request->boolean('remove_cover')) {
            $filesToDelete[] = $post->cover_image;
            $post->cover_image = null;
        }

        DB::transaction(function () use ($post, $data, $rows, $wasPublished, $oldFingerprint, $original, &$filesToDelete) {
            $textChanged = $post->isDirty(['title', 'excerpt']) || Html::fingerprint($post->body) !== $oldFingerprint;
            $contentChanged = $textChanged || $post->isDirty(['slug', 'cover_image']);
            $existed = $post->exists;
            $post->save();

            // Historial: guardar cómo era antes de este cambio.
            if ($existed && $textChanged) {
                $post->snapshot($original);
            }

            $tagChanges = $post->tags()->sync(Tag::idsFromString($data['tags'] ?? ''));
            Tag::deleteOrphans();
            Series::deleteOrphans();

            foreach ($rows as $row) {
                if ($row['delete']) {
                    $filesToDelete[] = $row['model']->cover_image;
                    $row['model']->delete();

                    continue;
                }
                $update = $row['model'] ?? $post->updates()->make();
                $update->fill(['title' => $row['title'], 'body' => $row['body']]);
                if ($row['file']) {
                    $filesToDelete[] = $update->cover_image;
                    $update->cover_image = Images::store($row['file'], 2000);
                } elseif ($row['remove_cover']) {
                    $filesToDelete[] = $update->cover_image;
                    $update->cover_image = null;
                }
                $update->save();
            }

            $post->refreshSearchText();

            // Fecha de edición: solo si el post ya estaba publicado y su contenido original cambió.
            $tagsChanged = $tagChanges['attached'] || $tagChanges['detached'];
            if ($wasPublished && $post->published_at && ($contentChanged || $tagsChanged)) {
                $post->forceFill(['edited_at' => now()])->save();
            }
        });

        Storage::disk('uploads')->delete(array_values(array_filter($filesToDelete)));

        return match (true) {
            ! $wasPublished && $post->published_at !== null => '¡Publicado! Ya se ve en el blog.',
            $wasPublished && $post->published_at === null => 'Pasado a borrador: ya no se ve en el blog.',
            $post->published_at !== null => 'Cambios guardados y publicados.',
            default => 'Borrador guardado. Solo tú lo ves.',
        };
    }
}
