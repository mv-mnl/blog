@extends('layout')

@section('title', 'Posts')

@section('content')
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <h1 class="text-2xl font-bold">Posts</h1>
        <form method="get" action="{{ route('posts.index') }}" class="flex gap-2">
            <input type="search" name="q" value="{{ $q }}" placeholder="Buscar…"
                   class="w-56 rounded-md border border-stone-300 bg-white px-3 py-1.5 text-sm dark:border-stone-700 dark:bg-stone-900">
            <button class="rounded-md border border-stone-300 px-3 py-1.5 text-sm hover:bg-stone-200 dark:border-stone-700 dark:hover:bg-stone-800">Buscar</button>
        </form>
    </div>

    @if ($posts->isEmpty())
        <div class="rounded-lg border border-dashed border-stone-300 p-12 text-center dark:border-stone-700">
            @if ($q)
                <p class="text-stone-500">Ningún post coincide con «{{ $q }}».</p>
            @else
                <p class="mb-4 text-stone-500">Todavía no has escrito nada.</p>
                <a href="{{ route('posts.create') }}" class="font-medium text-amber-700 hover:underline dark:text-amber-400">Escribe tu primer post →</a>
            @endif
        </div>
    @else
        <div class="overflow-x-auto rounded-lg border border-stone-200 bg-white dark:border-stone-800 dark:bg-stone-900">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-stone-200 text-xs uppercase tracking-wide text-stone-500 dark:border-stone-800">
                    <tr>
                        <th class="px-4 py-3 font-medium">Título</th>
                        <th class="px-4 py-3 font-medium">Estado</th>
                        <th class="px-4 py-3 font-medium">Etiquetas</th>
                        <th class="px-4 py-3 font-medium">Publicado</th>
                        <th class="px-4 py-3 font-medium">Editado</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-stone-100 dark:divide-stone-800">
                    @foreach ($posts as $post)
                        @php
                            $badge = match ($post->status()) {
                                'Publicado' => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300',
                                default => 'bg-stone-200 text-stone-700 dark:bg-stone-800 dark:text-stone-300',
                            };
                        @endphp
                        <tr class="hover:bg-stone-50 dark:hover:bg-stone-800/50">
                            <td class="px-4 py-3">
                                <a href="{{ route('posts.edit', $post) }}" class="font-medium hover:underline">{{ $post->title }}</a>
                                <div class="text-xs text-stone-500">
                                    /posts/{{ $post->slug }}
                                    @if ($post->updates_count)
                                        · {{ $post->updates_count }} {{ $post->updates_count === 1 ? 'actualización' : 'actualizaciones' }}
                                    @endif
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <span class="rounded-full px-2 py-0.5 text-xs font-medium {{ $badge }}">{{ $post->status() }}</span>
                            </td>
                            <td class="px-4 py-3 text-stone-500">{{ $post->tags->pluck('name')->join(', ') ?: '—' }}</td>
                            <td class="whitespace-nowrap px-4 py-3 text-stone-500">{{ $post->published_at?->translatedFormat('j M Y, H:i') ?? '—' }}</td>
                            <td class="whitespace-nowrap px-4 py-3 text-stone-500" title="{{ $post->edited_at?->translatedFormat('j M Y, H:i') }}">{{ $post->edited_at?->diffForHumans() ?? '—' }}</td>
                            <td class="whitespace-nowrap px-4 py-3 text-right">
                                @if ($post->status() === 'Publicado')
                                    <a href="{{ config('app.blog_url') }}/posts/{{ $post->slug }}" target="_blank" class="mr-3 text-stone-500 hover:underline">Ver</a>
                                @endif
                                <form method="post" action="{{ route('posts.destroy', $post) }}" class="inline"
                                      onsubmit="return confirm('¿Eliminar «{{ addslashes($post->title) }}»? No se puede deshacer.')">
                                    @csrf @method('delete')
                                    <button class="text-red-600 hover:underline dark:text-red-400">Eliminar</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if ($posts->hasPages())
            <div class="mt-6 flex justify-between text-sm">
                @if ($posts->previousPageUrl())
                    <a href="{{ $posts->previousPageUrl() }}" class="hover:underline">← Anteriores</a>
                @else <span></span> @endif
                <span class="text-stone-500">Página {{ $posts->currentPage() }} de {{ $posts->lastPage() }}</span>
                @if ($posts->nextPageUrl())
                    <a href="{{ $posts->nextPageUrl() }}" class="hover:underline">Siguientes →</a>
                @else <span></span> @endif
            </div>
        @endif
    @endif
@endsection
