<!doctype html>
<html lang="es" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('title', 'Panel') · Admin del blog</title>
    <script>
        (() => {
            const m = matchMedia('(prefers-color-scheme: dark)');
            const apply = () => document.documentElement.classList.toggle('dark', m.matches);
            apply();
            m.addEventListener('change', apply);
        })();
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-full bg-stone-100 text-stone-900 antialiased dark:bg-stone-950 dark:text-stone-100">
    <header class="border-b border-stone-200 bg-white dark:border-stone-800 dark:bg-stone-900">
        <div class="mx-auto flex max-w-6xl items-center justify-between gap-4 px-4 py-3">
            <a href="{{ route('posts.index') }}" class="flex items-center gap-2 font-semibold">
                <span class="rounded bg-amber-500 px-1.5 py-0.5 text-xs font-bold uppercase tracking-wide text-stone-950">Admin</span>
                Blog
            </a>
            <nav class="flex items-center gap-4 text-sm">
                <a href="{{ route('posts.index') }}" class="hover:underline">Posts</a>
                @php $pendingComments = \App\Models\Comment::where('status', 'pending')->count(); @endphp
                <a href="{{ route('comments.index') }}" class="hover:underline">
                    Comentarios
                    @if ($pendingComments)
                        <span class="ml-0.5 rounded-full bg-amber-500 px-1.5 py-0.5 text-xs font-bold text-stone-950">{{ $pendingComments }}</span>
                    @endif
                </a>
                <a href="{{ route('stats.index') }}" class="hover:underline">Estadísticas</a>
                <a href="{{ config('app.blog_url') }}" target="_blank" class="hover:underline">Ver blog ↗</a>
                <a href="{{ route('posts.create') }}" class="rounded-md bg-stone-900 px-3 py-1.5 font-medium text-white hover:bg-stone-700 dark:bg-stone-100 dark:text-stone-900 dark:hover:bg-stone-300">Nuevo post</a>
            </nav>
        </div>
    </header>

    <main class="@hasSection('flush') @else mx-auto max-w-6xl px-4 py-8 @endif">
        @if ($errors->any() && ! $errors->hasAny(['title', 'body']))
            <div class="mx-auto mt-6 max-w-3xl px-4">
                <div class="rounded-md border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-900 dark:border-red-800 dark:bg-red-950 dark:text-red-200">
                    <p class="font-medium">No se pudo guardar:</p>
                    <ul class="mt-1 list-inside list-disc">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        @endif

        @yield('content')
    </main>

    @if (session('status'))
        <div data-toast role="status"
             class="fixed right-4 bottom-4 z-50 rounded-lg bg-stone-900 px-4 py-3 text-sm font-medium text-white shadow-lg transition duration-500 dark:bg-stone-100 dark:text-stone-900">
            ✓ {{ session('status') }}
        </div>
    @endif
</body>
</html>
