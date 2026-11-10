@extends('layout')

@section('title', $post->exists ? 'Editar: '.$post->title : 'Nuevo post')
@section('flush', true)

@php
    $isPublished = $post->published_at !== null;
    $publicUrl = config('app.blog_url').'/posts/';

    // Filas de actualizaciones: si falló la validación, se reconstruyen desde lo enviado.
    $existing = $post->updates?->keyBy('id') ?? collect();
    $fmt = fn ($d) => $d?->translatedFormat('j \d\e F \d\e Y, H:i');
    $updateRows = old('updates') !== null
        ? collect(old('updates'))->map(fn ($r, $k) => [
            'key' => $k,
            'id' => $r['id'] ?? null,
            'title' => $r['title'] ?? '',
            'body' => $r['body'] ?? '',
            'cover' => isset($r['id']) ? $existing->get($r['id'])?->coverUrl() : null,
            'date' => isset($r['id']) ? $fmt($existing->get($r['id'])?->created_at) : null,
            'delete' => ! empty($r['delete']),
        ])->values()
        : $existing->values()->map(fn ($u) => [
            'key' => 'u'.$u->id,
            'id' => $u->id,
            'title' => $u->title,
            'body' => $u->body,
            'cover' => $u->coverUrl(),
            'date' => $fmt($u->created_at),
            'delete' => false,
        ]);
@endphp

@section('content')
<form method="post" enctype="multipart/form-data" data-post-form data-published="{{ $isPublished ? 1 : 0 }}"
      data-post-id="{{ $post->id }}" data-updated-at="{{ $post->updated_at ? $post->updated_at->getTimestampMs() : 0 }}"
      @if ($errors->any()) data-has-errors="1" @endif
      action="{{ $post->exists ? route('posts.update', $post) : route('posts.store') }}">
    @csrf
    @if ($post->exists) @method('put') @endif

    {{-- ========== Barra superior: estado + acciones ========== --}}
    <div class="sticky top-0 z-20 border-b border-stone-200 bg-stone-100/90 backdrop-blur dark:border-stone-800 dark:bg-stone-950/90">
        <div class="mx-auto flex h-14 max-w-5xl items-center justify-between gap-3 px-4">
            <div class="flex min-w-0 items-center gap-3 text-sm">
                <a href="{{ route('posts.index') }}" class="shrink-0 text-stone-500 hover:text-stone-900 dark:hover:text-stone-100">← Posts</a>
                @if ($isPublished)
                    <a href="{{ $publicUrl.$post->slug }}" target="_blank"
                       class="truncate rounded-full bg-emerald-100 px-3 py-1 font-medium text-emerald-800 hover:bg-emerald-200 dark:bg-emerald-950 dark:text-emerald-300">
                        ● Publicado · visible en el blog ↗
                    </a>
                @else
                    <span class="truncate rounded-full bg-stone-200 px-3 py-1 font-medium text-stone-700 dark:bg-stone-800 dark:text-stone-300">
                        ○ Borrador · solo tú lo ves
                    </span>
                @endif
                <span class="hidden text-xs text-amber-700 dark:text-amber-400" data-dirty-note>· cambios sin guardar</span>
                <span class="hidden text-xs text-stone-400" data-autosave-note title="Se guarda una copia en este navegador por si se cierra la pestaña"></span>
            </div>

            <div class="flex shrink-0 items-center gap-2">
                @if ($post->exists && $post->revisions->isNotEmpty())
                    <button type="button" data-open-history
                            class="rounded-md px-3 py-2 text-sm text-stone-600 hover:bg-stone-200 dark:text-stone-300 dark:hover:bg-stone-800"
                            title="Versiones anteriores de este post">
                        ↺ Historial <span class="text-stone-400">{{ $post->revisions->count() }}</span>
                    </button>
                @endif
                <button type="button" data-open-settings
                        class="rounded-md px-3 py-2 text-sm text-stone-600 hover:bg-stone-200 dark:text-stone-300 dark:hover:bg-stone-800">
                    ⚙ Ajustes
                </button>
                @if ($isPublished)
                    <button name="status" value="published" data-save-default
                            class="rounded-md bg-amber-500 px-4 py-2 text-sm font-semibold text-stone-950 hover:bg-amber-400">
                        Guardar cambios
                    </button>
                @else
                    <button name="status" value="draft" data-save-default
                            class="rounded-md border border-stone-300 px-4 py-2 text-sm font-medium hover:bg-stone-200 dark:border-stone-700 dark:hover:bg-stone-800">
                        Guardar borrador
                    </button>
                    <button type="button" data-open-publish
                            class="rounded-md bg-amber-500 px-4 py-2 text-sm font-semibold text-stone-950 hover:bg-amber-400">
                        Publicar…
                    </button>
                @endif
            </div>
        </div>
        {{-- Barra de formato única: actúa sobre el texto donde está el cursor --}}
        <div class="border-t border-stone-200 dark:border-stone-800">
            <div data-global-toolbar class="mx-auto flex max-w-5xl flex-wrap items-center justify-center gap-0.5 px-2 py-1.5"></div>
        </div>
    </div>

    {{-- ========== El post ========== --}}
    <article class="mx-auto max-w-3xl px-4 pt-8">
        <div data-restore-banner class="mb-6 hidden rounded-lg border border-sky-300 bg-sky-50 px-4 py-3 text-sm text-sky-900 dark:border-sky-800 dark:bg-sky-950 dark:text-sky-200">
            Hay cambios sin guardar de este post del <strong data-restore-time></strong> (copia automática de este navegador).
            <span class="mt-2 flex gap-3">
                <button type="button" data-restore class="font-semibold underline">Recuperarlos</button>
                <button type="button" data-discard class="underline">Descartar</button>
            </span>
        </div>
        @if ($errors->has('body') || $errors->has('title'))
            <div class="mb-6 rounded-md border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-900 dark:border-red-800 dark:bg-red-950 dark:text-red-200">
                {{ $errors->first('title') ?: $errors->first('body') }}
            </div>
        @endif

        @include('posts._cover', ['name' => 'cover', 'removeName' => 'remove_cover', 'url' => $post->coverUrl()])

        <textarea name="title" rows="1" maxlength="200" required data-title data-autosize
                  placeholder="Título"
                  class="mt-8 block w-full resize-none overflow-hidden border-0 bg-transparent p-0 font-serif text-4xl leading-tight font-bold placeholder:text-stone-300 focus:ring-0 focus:outline-none sm:text-5xl dark:placeholder:text-stone-700"
                  @if (! $post->exists) autofocus @endif>{{ old('title', $post->title) }}</textarea>

        <textarea name="excerpt" rows="1" maxlength="300" data-autosize data-subtitle
                  placeholder="Subtítulo o resumen (opcional): aparece en la portada del blog"
                  class="mt-3 block w-full resize-none overflow-hidden border-0 bg-transparent p-0 text-xl leading-relaxed text-stone-600 placeholder:text-stone-300 focus:ring-0 focus:outline-none dark:text-stone-400 dark:placeholder:text-stone-700">{{ old('excerpt', $post->excerpt) }}</textarea>

        @if ($post->published_at)
            <p class="mt-4 text-sm text-stone-500">
                Publicado el {{ $fmt($post->published_at) }}
                @if ($post->edited_at) · Editado el {{ $fmt($post->edited_at) }} @endif
            </p>
        @endif

        <div class="mt-8">
            @include('posts._editor', ['name' => 'body', 'value' => old('body', $post->body), 'placeholder' => 'Empieza a escribir… (puedes pegar o arrastrar imágenes)'])
        </div>
    </article>

    {{-- ========== Actualizaciones ========== --}}
    <section class="mx-auto mt-16 max-w-3xl px-4 pb-24">
        <div class="mb-2 flex items-center gap-3">
            <h2 class="text-sm font-semibold tracking-widest text-stone-500 uppercase">Actualizaciones</h2>
            <span class="h-px flex-1 bg-stone-300 dark:bg-stone-800"></span>
        </div>
        <p class="mb-8 text-sm text-stone-500">
            ¿Cambiaste de opinión o aprendiste algo nuevo? Añade una actualización: el post de arriba se queda como está
            y la novedad aparece debajo, con su fecha, como una línea de tiempo.
        </p>

        <ol class="space-y-8 border-l-2 border-amber-300 pl-8 empty:hidden dark:border-amber-800" data-updates-list>
            @foreach ($updateRows as $row)
                @include('posts._update', ['row' => $row])
            @endforeach
        </ol>

        <template data-update-template>
            @include('posts._update', ['row' => ['key' => '__KEY__', 'id' => null, 'title' => '', 'body' => '', 'cover' => null, 'date' => null, 'delete' => false]])
        </template>

        <button type="button" data-add-update
                class="mt-8 w-full rounded-xl border-2 border-dashed border-stone-300 py-4 text-sm font-medium text-stone-600 hover:border-amber-400 hover:bg-amber-50/50 hover:text-amber-800 dark:border-stone-700 dark:text-stone-400 dark:hover:bg-amber-950/20 dark:hover:text-amber-300">
            + Añadir actualización
        </button>
    </section>

    {{-- ========== Ajustes / Publicar ========== --}}
    <dialog data-settings
            class="m-auto w-full max-w-lg rounded-xl border border-stone-200 bg-white p-0 text-stone-900 shadow-2xl backdrop:bg-stone-950/40 dark:border-stone-800 dark:bg-stone-900 dark:text-stone-100">
        <div class="space-y-5 p-6">
            <div>
                <h2 class="text-lg font-bold" data-settings-title>Ajustes del post</h2>
                <p class="mt-1 hidden text-sm text-stone-500" data-publish-intro>
                    Revisa estos datos. Al publicar, el post aparecerá en la portada del blog con la fecha de hoy.
                </p>
            </div>

            <div>
                <label for="tags" class="mb-1 block text-sm font-medium">Etiquetas</label>
                <input id="tags" name="tags" value="{{ old('tags', $post->tags?->pluck('name')->join(', ')) }}"
                       placeholder="laravel, docker, notas"
                       class="w-full rounded-md border border-stone-300 bg-white px-3 py-2 text-sm focus:border-amber-500 focus:ring-2 focus:ring-amber-500/30 focus:outline-none dark:border-stone-700 dark:bg-stone-950">
                <p class="mt-1 text-xs text-stone-500">Separadas por comas. Agrupan posts del mismo tema: quien hace clic en una ve todos los que la tienen.</p>
            </div>

            <div>
                <label for="series" class="mb-1 block text-sm font-medium">Serie (opcional)</label>
                <input id="series" name="series" list="series-list" value="{{ old('series', $post->series?->title) }}"
                       placeholder="p. ej. «Aprendiendo Docker»"
                       class="w-full rounded-md border border-stone-300 bg-white px-3 py-2 text-sm focus:border-amber-500 focus:ring-2 focus:ring-amber-500/30 focus:outline-none dark:border-stone-700 dark:bg-stone-950">
                <datalist id="series-list">
                    @foreach ($allSeries as $s)<option value="{{ $s }}">@endforeach
                </datalist>
                <p class="mt-1 text-xs text-stone-500">Para posts que van en varias partes. Escribe un nombre nuevo o elige uno existente: el blog los mostrará como «Parte 1, 2, 3…» enlazadas.</p>
            </div>

            <div>
                <label for="slug" class="mb-1 block text-sm font-medium">Dirección (URL)</label>
                <div class="flex items-center rounded-md border border-stone-300 bg-stone-50 text-sm focus-within:border-amber-500 focus-within:ring-2 focus-within:ring-amber-500/30 dark:border-stone-700 dark:bg-stone-950">
                    <span class="truncate py-2 pl-3 text-stone-500">{{ $publicUrl }}</span>
                    <input id="slug" name="slug" value="{{ old('slug', $post->slug) }}" data-slug
                           class="min-w-0 flex-1 border-0 bg-transparent py-2 pr-3 focus:ring-0 focus:outline-none">
                </div>
                <p class="mt-1 text-xs text-stone-500">
                    Se genera sola a partir del título. Cámbiala solo si quieres una más corta.
                    @if ($isPublished) <strong>Ojo:</strong> si la cambias, los enlaces que ya compartiste dejarán de funcionar. @endif
                </p>
                @error('slug') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
        </div>

        <div class="flex items-center justify-between gap-3 rounded-b-xl border-t border-stone-200 bg-stone-50 px-6 py-4 dark:border-stone-800 dark:bg-stone-950">
            <div>
                @if ($isPublished)
                    <button name="status" value="draft" data-unpublish
                            class="text-sm text-red-600 hover:underline dark:text-red-400">
                        Despublicar (pasar a borrador)
                    </button>
                @endif
            </div>
            <div class="flex gap-2">
                <button type="button" data-close-settings
                        class="rounded-md px-4 py-2 text-sm hover:bg-stone-200 dark:hover:bg-stone-800">Listo</button>
                <button name="status" value="published" data-publish-now
                        class="hidden rounded-md bg-amber-500 px-4 py-2 text-sm font-semibold text-stone-950 hover:bg-amber-400">
                    Publicar ahora
                </button>
            </div>
        </div>
    </dialog>
</form>

@if ($post->exists && $post->revisions->isNotEmpty())
    {{-- ========== Historial de versiones ========== --}}
    <dialog data-history
            class="m-auto h-[85vh] w-full max-w-5xl rounded-xl border border-stone-200 bg-white p-0 text-stone-900 shadow-2xl backdrop:bg-stone-950/40 dark:border-stone-800 dark:bg-stone-900 dark:text-stone-100">
        <div class="flex h-full flex-col">
            <div class="flex items-center justify-between border-b border-stone-200 px-6 py-4 dark:border-stone-800">
                <div>
                    <h2 class="text-lg font-bold">Historial de versiones</h2>
                    <p class="text-sm text-stone-500">Así era el post antes de cada cambio. Restaurar no borra nada: la versión actual también se guarda aquí.</p>
                </div>
                <button type="button" data-close-history class="rounded-md px-3 py-2 text-sm hover:bg-stone-200 dark:hover:bg-stone-800">Cerrar</button>
            </div>
            <div class="grid min-h-0 flex-1 md:grid-cols-[240px_1fr]">
                <ul class="overflow-y-auto border-r border-stone-200 p-2 dark:border-stone-800">
                    @foreach ($post->revisions as $rev)
                        <li>
                            <button type="button" data-revision
                                    data-url="{{ route('posts.revision', [$post, $rev]) }}"
                                    data-restore="{{ route('posts.restore', [$post, $rev]) }}"
                                    class="w-full rounded-md px-3 py-2 text-left hover:bg-stone-100 data-[active]:bg-amber-100 dark:hover:bg-stone-800 dark:data-[active]:bg-amber-950">
                                <span class="block text-sm font-medium">{{ $rev->created_at->translatedFormat('j M Y, H:i') }}</span>
                                <span class="block truncate text-xs text-stone-500">{{ $rev->title }}</span>
                            </button>
                        </li>
                    @endforeach
                </ul>
                <div class="flex min-h-0 flex-col">
                    <div data-history-preview class="prose prose-stone min-h-0 max-w-none flex-1 overflow-y-auto p-6 dark:prose-invert">
                        <p class="text-stone-400">Elige una versión de la lista para verla.</p>
                    </div>
                    <form method="post" data-history-restore class="hidden border-t border-stone-200 px-6 py-4 text-right dark:border-stone-800">
                        @csrf
                        <button class="rounded-md bg-amber-500 px-4 py-2 text-sm font-semibold text-stone-950 hover:bg-amber-400">Restaurar esta versión</button>
                    </form>
                </div>
            </div>
        </div>
    </dialog>
@endif

@if ($post->exists)
    <div class="mx-auto max-w-3xl px-4 pb-16">
        <form method="post" action="{{ route('posts.destroy', $post) }}"
              onsubmit="return confirm('¿Eliminar «{{ addslashes($post->title) }}» y todas sus actualizaciones? No se puede deshacer.')">
            @csrf @method('delete')
            <button class="text-sm text-stone-400 hover:text-red-600">Eliminar este post</button>
        </form>
    </div>
@endif
@endsection
