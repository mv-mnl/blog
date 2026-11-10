@extends('layout')

@section('title', isset($tag) ? '#'.$tag->name : ($q ? 'Búsqueda: '.$q : null))

@section('content')
    @if (isset($tag))
        <h1 class="mb-8 text-sm font-medium uppercase tracking-widest text-stone-500">
            Etiqueta <span class="text-amber-700 dark:text-amber-400">#{{ $tag->name }}</span>
            · <a href="{{ route('home') }}" class="normal-case tracking-normal hover:underline">ver todo</a>
        </h1>
    @elseif ($q)
        <h1 class="mb-8 text-sm font-medium uppercase tracking-widest text-stone-500">
            {{ $posts->total() }} {{ $posts->total() === 1 ? 'resultado' : 'resultados' }} para «{{ $q }}»
            · <a href="{{ route('home') }}" class="normal-case tracking-normal hover:underline">limpiar</a>
        </h1>
    @endif

    @forelse ($posts as $post)
        <article class="group border-b border-stone-200 py-8 first:pt-0 dark:border-stone-800">
            @if ($post->coverUrl())
                <a href="{{ route('posts.show', $post->slug) }}" class="mb-5 block overflow-hidden rounded-xl">
                    <img src="{{ $post->coverUrl() }}" alt="" loading="lazy"
                         class="aspect-[2/1] w-full object-cover transition duration-500 group-hover:scale-[1.02]">
                </a>
            @endif
            <div class="mb-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-stone-500">
                <time datetime="{{ $post->published_at->toIso8601String() }}">{{ $post->published_at->translatedFormat('j \d\e F, Y') }}</time>
                <span aria-hidden="true">·</span>
                <span>{{ $post->readingMinutes() }} min de lectura</span>
                @if ($post->edited_at)
                    <span aria-hidden="true">·</span>
                    <span class="italic">editado</span>
                @endif
                @if ($post->updates_count)
                    <span aria-hidden="true">·</span>
                    <span class="font-medium text-amber-700 dark:text-amber-400">
                        {{ $post->updates_count }} {{ $post->updates_count === 1 ? 'actualización' : 'actualizaciones' }}
                    </span>
                @endif
            </div>
            @if ($post->series)
                <a href="{{ route('series.show', $post->series->slug) }}" class="mb-1 inline-block text-xs font-semibold tracking-widest text-amber-700 uppercase hover:underline dark:text-amber-400">Serie: {{ $post->series->title }}</a>
            @endif
            <h2 class="font-serif text-2xl font-bold leading-snug sm:text-3xl">
                <a href="{{ route('posts.show', $post->slug) }}" class="hover:text-amber-700 dark:hover:text-amber-400">{{ $post->title }}</a>
            </h2>
            <p class="mt-3 leading-relaxed text-stone-600 dark:text-stone-400">{{ $post->summary() }}</p>
            @include('blog.tags', ['tags' => $post->tags])
        </article>
    @empty
        <p class="py-16 text-center text-stone-500">
            {{ $q ? 'No encontré nada con esa búsqueda.' : 'Todavía no hay posts publicados.' }}
        </p>
    @endforelse

    @if ($posts->hasPages())
        <nav class="mt-10 flex items-center justify-between text-sm" aria-label="Paginación">
            @if ($posts->previousPageUrl())
                <a href="{{ $posts->previousPageUrl() }}" class="rounded-full border border-stone-300 px-4 py-2 hover:border-amber-500 dark:border-stone-700">← Más recientes</a>
            @else <span></span> @endif
            <span class="text-stone-500">{{ $posts->currentPage() }} / {{ $posts->lastPage() }}</span>
            @if ($posts->nextPageUrl())
                <a href="{{ $posts->nextPageUrl() }}" class="rounded-full border border-stone-300 px-4 py-2 hover:border-amber-500 dark:border-stone-700">Anteriores →</a>
            @else <span></span> @endif
        </nav>
    @endif
@endsection
