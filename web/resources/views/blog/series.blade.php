@extends('layout')

@section('title', 'Serie: '.$series->title)

@section('content')
    <p class="text-sm font-medium tracking-widest text-stone-500 uppercase">Serie · {{ $posts->count() }} {{ $posts->count() === 1 ? 'parte' : 'partes' }}</p>
    <h1 class="mt-2 mb-10 font-serif text-4xl font-bold">{{ $series->title }}</h1>

    <ol class="space-y-6">
        @foreach ($posts as $p)
            <li class="flex gap-5">
                <span class="flex size-10 shrink-0 items-center justify-center rounded-full bg-amber-100 font-serif text-lg font-bold text-amber-800 dark:bg-amber-950 dark:text-amber-300">{{ $loop->iteration }}</span>
                <div>
                    <a href="{{ route('posts.show', $p->slug) }}" class="font-serif text-xl font-bold hover:text-amber-700 dark:hover:text-amber-400">{{ $p->title }}</a>
                    <p class="mt-1 text-stone-600 dark:text-stone-400">{{ $p->summary() }}</p>
                    <time class="mt-1 block text-sm text-stone-500">{{ $p->published_at->translatedFormat('j \d\e F, Y') }}</time>
                </div>
            </li>
        @endforeach
    </ol>
@endsection
