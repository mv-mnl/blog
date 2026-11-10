@extends('layout')

@section('title', $post->title)
@section('description', $post->summary())

@push('meta')
    <meta property="og:type" content="article">
    <meta property="og:title" content="{{ $post->title }}">
    <meta property="og:description" content="{{ $post->summary() }}">
    <meta property="article:published_time" content="{{ $post->published_at->toIso8601String() }}">
    @if ($post->edited_at)
        <meta property="article:modified_time" content="{{ $post->edited_at->toIso8601String() }}">
    @endif
    @if ($post->coverUrl())
        <meta property="og:image" content="{{ url($post->coverUrl()) }}">
    @endif
@endpush

@php
    $prose = 'prose prose-stone prose-lg max-w-none dark:prose-invert prose-headings:font-serif prose-headings:scroll-mt-6 prose-a:text-amber-700 dark:prose-a:text-amber-400 prose-img:rounded-lg prose-pre:bg-stone-900';
    $part = $seriesPosts->search(fn ($p) => $p->id === $post->id);
@endphp

@section('content')
    <article>
        <header class="mb-8">
            @if ($post->series)
                <a href="{{ route('series.show', $post->series->slug) }}" class="mb-3 inline-block text-xs font-semibold tracking-widest text-amber-700 uppercase hover:underline dark:text-amber-400">
                    Serie: {{ $post->series->title }} · Parte {{ $part + 1 }} de {{ $seriesPosts->count() }}
                </a>
            @endif
            <div class="mb-3 flex flex-wrap items-center gap-x-3 text-sm text-stone-500">
                <time datetime="{{ $post->published_at->toIso8601String() }}">{{ $post->published_at->translatedFormat('j \d\e F, Y') }}</time>
                <span aria-hidden="true">·</span>
                <span>{{ $post->readingMinutes() }} min de lectura</span>
                @if ($post->updates->isNotEmpty())
                    <span aria-hidden="true">·</span>
                    <a href="#actualizaciones" class="font-medium text-amber-700 hover:underline dark:text-amber-400">
                        {{ $post->updates->count() }} {{ $post->updates->count() === 1 ? 'actualización' : 'actualizaciones' }}
                    </a>
                @endif
                @if ($post->edited_at)
                    <span aria-hidden="true">·</span>
                    <span title="{{ $post->edited_at->translatedFormat('j \d\e F \d\e Y, H:i') }}">
                        Editado el <time datetime="{{ $post->edited_at->toIso8601String() }}">{{ $post->edited_at->translatedFormat('j \d\e F, Y') }}</time>
                    </span>
                @endif
                @if ($post->approvedComments->isNotEmpty())
                    <span aria-hidden="true">·</span>
                    <a href="#comentarios" class="hover:underline">{{ $post->approvedComments->count() }} {{ $post->approvedComments->count() === 1 ? 'comentario' : 'comentarios' }}</a>
                @endif
            </div>
            <h1 class="font-serif text-4xl font-bold leading-tight tracking-tight sm:text-5xl">{{ $post->title }}</h1>
            @if ($post->excerpt)
                <p class="mt-4 text-lg leading-relaxed text-stone-600 dark:text-stone-400">{{ $post->excerpt }}</p>
            @endif
            @include('blog.tags', ['tags' => $post->tags])
        </header>

        @if ($post->coverUrl())
            <img src="{{ $post->coverUrl() }}" alt="" class="mb-10 aspect-[2/1] w-full rounded-xl object-cover">
        @endif

        {{-- Índice: solo en posts con varias secciones --}}
        @if ($toc)
            <details open class="mb-10 rounded-xl border border-stone-200 bg-white px-5 py-4 dark:border-stone-800 dark:bg-stone-900">
                <summary class="cursor-pointer text-sm font-semibold tracking-widest text-stone-500 uppercase">En este post</summary>
                <ol class="mt-3 space-y-1.5 text-[15px]">
                    @foreach ($toc as [$level, $id, $text])
                        <li class="{{ $level === 3 ? 'pl-5 text-stone-500' : '' }}">
                            <a href="#{{ $id }}" class="hover:text-amber-700 hover:underline dark:hover:text-amber-400">{{ $text }}</a>
                        </li>
                    @endforeach
                </ol>
            </details>
        @endif

        <div class="{{ $prose }}">
            {!! $html !!}
        </div>

        @if ($post->updates->isNotEmpty())
            <section class="mt-14" aria-labelledby="actualizaciones">
                <h2 id="actualizaciones" class="mb-8 flex scroll-mt-6 items-center gap-3 text-sm font-semibold uppercase tracking-widest text-stone-500">
                    <span>Actualizaciones</span>
                    <span class="h-px flex-1 bg-stone-200 dark:bg-stone-800"></span>
                </h2>
                <ol class="relative space-y-10 border-l-2 border-amber-300 pl-8 dark:border-amber-800">
                    @foreach ($post->updates as $update)
                        <li class="relative scroll-mt-6" id="actualizacion-{{ $loop->iteration }}">
                            <span class="absolute top-1.5 -left-[41px] size-4 rounded-full border-[3px] border-amber-400 bg-stone-50 dark:bg-stone-950" aria-hidden="true"></span>
                            <div class="flex flex-wrap items-baseline gap-x-2 text-sm">
                                <a href="#actualizacion-{{ $loop->iteration }}" class="font-semibold text-amber-700 hover:underline dark:text-amber-400">
                                    <time datetime="{{ $update->created_at->toIso8601String() }}">{{ $update->created_at->translatedFormat('j \d\e F, Y') }}</time>
                                </a>
                                @if ($update->created_at->gt($post->published_at))
                                    <span class="text-stone-400">· {{ $update->created_at->diffForHumans($post->published_at, \Carbon\CarbonInterface::DIFF_ABSOLUTE) }} después de publicar</span>
                                @endif
                            </div>
                            <h3 class="mt-2 font-serif text-2xl font-bold leading-snug sm:text-3xl">{{ $update->title }}</h3>
                            @if ($update->coverUrl())
                                <img src="{{ $update->coverUrl() }}" alt="" loading="lazy" class="mt-5 aspect-[2/1] w-full rounded-xl object-cover">
                            @endif
                            <div class="{{ $prose }} mt-4">
                                {!! $update->html('act'.$loop->iteration.'-') !!}
                            </div>
                        </li>
                    @endforeach
                </ol>
            </section>
        @endif
    </article>

    {{-- Serie --}}
    @if ($seriesPosts->count() > 1)
        <nav class="mt-14 rounded-xl border border-amber-200 bg-amber-50/60 p-6 dark:border-amber-900 dark:bg-amber-950/20" aria-label="Serie">
            <p class="text-xs font-semibold tracking-widest text-amber-800 uppercase dark:text-amber-400">Serie · {{ $seriesPosts->count() }} partes</p>
            <a href="{{ route('series.show', $post->series->slug) }}" class="mt-1 block font-serif text-xl font-bold hover:underline">{{ $post->series->title }}</a>
            <ol class="mt-4 space-y-2">
                @foreach ($seriesPosts as $p)
                    <li class="flex gap-3 text-[15px]">
                        <span class="w-6 shrink-0 text-right font-semibold text-stone-400">{{ $loop->iteration }}.</span>
                        @if ($p->id === $post->id)
                            <span class="font-semibold">{{ $p->title }} <span class="font-normal text-stone-500">(estás aquí)</span></span>
                        @else
                            <a href="{{ route('posts.show', $p->slug) }}" class="hover:text-amber-700 hover:underline dark:hover:text-amber-400">{{ $p->title }}</a>
                        @endif
                    </li>
                @endforeach
            </ol>
            @if (isset($seriesPosts[$part + 1]))
                <a href="{{ route('posts.show', $seriesPosts[$part + 1]->slug) }}" class="mt-5 inline-block rounded-full bg-amber-500 px-5 py-2 text-sm font-semibold text-stone-950 hover:bg-amber-400">
                    Siguiente parte →
                </a>
            @endif
        </nav>
    @endif

    {{-- Comentarios --}}
    <section id="comentarios" class="mt-16 scroll-mt-6" aria-labelledby="comentarios-titulo">
        <h2 id="comentarios-titulo" class="mb-6 flex items-center gap-3 text-sm font-semibold tracking-widest text-stone-500 uppercase">
            <span>Comentarios{{ $post->approvedComments->isNotEmpty() ? ' ('.$post->approvedComments->count().')' : '' }}</span>
            <span class="h-px flex-1 bg-stone-200 dark:bg-stone-800"></span>
        </h2>

        @foreach ($post->approvedComments as $comment)
            <div class="mb-6 border-b border-stone-200 pb-6 dark:border-stone-800">
                <div class="mb-1 flex items-baseline gap-2 text-sm">
                    <span class="font-semibold">{{ $comment->name }}</span>
                    <time class="text-stone-500" datetime="{{ $comment->created_at->toIso8601String() }}">{{ $comment->created_at->translatedFormat('j \d\e F, Y') }}</time>
                </div>
                <p class="whitespace-pre-line leading-relaxed text-stone-700 dark:text-stone-300">{{ $comment->body }}</p>
            </div>
        @endforeach

        @if ($commentSent)
            <div class="rounded-lg border border-emerald-300 bg-emerald-50 px-4 py-3 text-sm text-emerald-900 dark:border-emerald-800 dark:bg-emerald-950 dark:text-emerald-200">
                ¡Gracias! Tu comentario se publicará en cuanto lo revise.
            </div>
        @else
            <form method="post" action="{{ route('comments.store', $post->slug) }}" class="space-y-3">
                @if ($commentErrors->any())
                    <div class="rounded-lg border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-900 dark:border-red-800 dark:bg-red-950 dark:text-red-200">
                        @foreach ($commentErrors->all() as $error) <p>{{ $error }}</p> @endforeach
                    </div>
                @endif
                <input type="hidden" name="t" value="{{ $commentToken }}">
                {{-- Campo trampa: invisible para personas, los bots lo rellenan --}}
                <div class="absolute -left-[9999px]" aria-hidden="true">
                    <label>No rellenes esto <input name="website" tabindex="-1" autocomplete="off"></label>
                </div>
                <input name="name" required maxlength="60" value="{{ $old['name'] ?? '' }}" placeholder="Tu nombre" autocomplete="name"
                       class="w-full rounded-lg border border-stone-300 bg-white px-4 py-2.5 focus:border-amber-500 focus:ring-2 focus:ring-amber-500/30 focus:outline-none sm:w-72 dark:border-stone-700 dark:bg-stone-900">
                <textarea name="body" required minlength="2" maxlength="3000" rows="4" placeholder="Escribe tu comentario…"
                          class="w-full rounded-lg border border-stone-300 bg-white px-4 py-3 focus:border-amber-500 focus:ring-2 focus:ring-amber-500/30 focus:outline-none dark:border-stone-700 dark:bg-stone-900">{{ $old['body'] ?? '' }}</textarea>
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <p class="text-xs text-stone-500">Los comentarios se revisan antes de publicarse. No se guarda tu email ni tu IP.</p>
                    <button class="rounded-full bg-stone-900 px-5 py-2 text-sm font-semibold text-white hover:bg-stone-700 dark:bg-stone-100 dark:text-stone-900 dark:hover:bg-stone-300">Enviar comentario</button>
                </div>
            </form>
        @endif
    </section>

    {{-- Relacionados --}}
    @if ($related->isNotEmpty())
        <section class="mt-16" aria-label="Posts relacionados">
            <h2 class="mb-6 flex items-center gap-3 text-sm font-semibold tracking-widest text-stone-500 uppercase">
                <span>También te puede interesar</span>
                <span class="h-px flex-1 bg-stone-200 dark:bg-stone-800"></span>
            </h2>
            <div class="grid gap-6 sm:grid-cols-3">
                @foreach ($related as $r)
                    <a href="{{ route('posts.show', $r->slug) }}" class="group block">
                        @if ($r->coverUrl())
                            <img src="{{ $r->coverUrl() }}" alt="" loading="lazy" class="mb-3 aspect-[2/1] w-full rounded-lg object-cover transition group-hover:opacity-90">
                        @else
                            <div class="mb-3 aspect-[2/1] w-full rounded-lg bg-gradient-to-br from-stone-200 to-stone-300 dark:from-stone-800 dark:to-stone-900"></div>
                        @endif
                        <h3 class="font-serif text-lg leading-snug font-bold group-hover:text-amber-700 dark:group-hover:text-amber-400">{{ $r->title }}</h3>
                        <time class="text-xs text-stone-500">{{ $r->published_at->translatedFormat('j M Y') }}</time>
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    @if ($newer || $older)
        <nav class="mt-16 grid gap-4 border-t border-stone-200 pt-8 sm:grid-cols-2 dark:border-stone-800" aria-label="Más posts">
            <div>
                @if ($older)
                    <span class="text-xs uppercase tracking-widest text-stone-500">← Anterior</span>
                    <a href="{{ route('posts.show', $older->slug) }}" class="mt-1 block font-serif text-lg font-semibold hover:text-amber-700 dark:hover:text-amber-400">{{ $older->title }}</a>
                @endif
            </div>
            <div class="sm:text-right">
                @if ($newer)
                    <span class="text-xs uppercase tracking-widest text-stone-500">Siguiente →</span>
                    <a href="{{ route('posts.show', $newer->slug) }}" class="mt-1 block font-serif text-lg font-semibold hover:text-amber-700 dark:hover:text-amber-400">{{ $newer->title }}</a>
                @endif
            </div>
        </nav>
    @endif
@endsection
