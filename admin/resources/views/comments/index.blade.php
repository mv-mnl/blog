@extends('layout')

@section('title', 'Comentarios')

@section('content')
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <h1 class="text-2xl font-bold">Comentarios</h1>
        <nav class="flex rounded-md border border-stone-300 p-0.5 text-sm dark:border-stone-700">
            @foreach (['pending' => 'Pendientes', 'approved' => 'Aprobados', 'spam' => 'Spam'] as $key => $label)
                <a href="{{ route('comments.index', ['estado' => $key]) }}"
                   class="rounded px-3 py-1.5 {{ $status === $key ? 'bg-stone-200 font-medium dark:bg-stone-800' : 'text-stone-500 hover:text-stone-900 dark:hover:text-stone-100' }}">
                    {{ $label }} <span class="text-stone-400">{{ $counts[$key] ?? 0 }}</span>
                </a>
            @endforeach
        </nav>
    </div>

    @if ($status === 'pending')
        <p class="mb-6 text-sm text-stone-500">Los comentarios nuevos esperan aquí hasta que los apruebes. No se ven en el blog hasta entonces.</p>
    @elseif ($status === 'spam' && ($counts['spam'] ?? 0) > 0)
        <form method="post" action="{{ route('comments.destroy-spam') }}" class="mb-6" onsubmit="return confirm('¿Eliminar todo el spam?')">
            @csrf @method('delete')
            <button class="text-sm text-red-600 hover:underline">Vaciar spam</button>
        </form>
    @endif

    <div class="space-y-4">
        @forelse ($comments as $comment)
            <article class="rounded-lg border border-stone-200 bg-white p-5 dark:border-stone-800 dark:bg-stone-900">
                <div class="mb-2 flex flex-wrap items-baseline justify-between gap-2 text-sm">
                    <div>
                        <span class="font-semibold">{{ $comment->name }}</span>
                        <span class="text-stone-500">en</span>
                        <a href="{{ route('posts.edit', $comment->post_id) }}" class="text-amber-700 hover:underline dark:text-amber-400">{{ $comment->post?->title }}</a>
                    </div>
                    <time class="text-stone-500" title="{{ $comment->created_at->translatedFormat('j M Y, H:i') }}">{{ $comment->created_at->diffForHumans() }}</time>
                </div>
                <p class="whitespace-pre-line text-stone-700 dark:text-stone-300">{{ $comment->body }}</p>
                <div class="mt-4 flex flex-wrap gap-2 text-sm">
                    @foreach (['approved' => ['Aprobar', 'bg-emerald-600 text-white hover:bg-emerald-500'], 'pending' => ['A pendientes', 'border border-stone-300 hover:bg-stone-100 dark:border-stone-700 dark:hover:bg-stone-800'], 'spam' => ['Es spam', 'border border-stone-300 hover:bg-stone-100 dark:border-stone-700 dark:hover:bg-stone-800']] as $to => [$label, $cls])
                        @continue($comment->status === $to)
                        <form method="post" action="{{ route('comments.update', $comment) }}">
                            @csrf @method('patch')
                            <input type="hidden" name="status" value="{{ $to }}">
                            <button class="rounded-md px-3 py-1.5 font-medium {{ $cls }}">{{ $label }}</button>
                        </form>
                    @endforeach
                    <form method="post" action="{{ route('comments.destroy', $comment) }}" onsubmit="return confirm('¿Eliminar este comentario?')">
                        @csrf @method('delete')
                        <button class="rounded-md px-3 py-1.5 text-red-600 hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-950">Eliminar</button>
                    </form>
                </div>
            </article>
        @empty
            <p class="rounded-lg border border-dashed border-stone-300 p-12 text-center text-stone-500 dark:border-stone-700">No hay comentarios aquí.</p>
        @endforelse
    </div>

    @if ($comments->hasPages())
        <div class="mt-6 flex justify-between text-sm">
            <a href="{{ $comments->previousPageUrl() }}" class="{{ $comments->onFirstPage() ? 'invisible' : 'hover:underline' }}">← Anteriores</a>
            <a href="{{ $comments->nextPageUrl() }}" class="{{ $comments->hasMorePages() ? 'hover:underline' : 'invisible' }}">Siguientes →</a>
        </div>
    @endif
@endsection
