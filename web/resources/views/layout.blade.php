<!doctype html>
<html lang="es" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@hasSection('title')@yield('title') · @endif{{ config('app.name') }}</title>
    <meta name="description" content="@yield('description', config('app.blog_description'))">
    <link rel="alternate" type="application/rss+xml" title="{{ config('app.name') }}" href="{{ route('feed') }}">
    @stack('meta')
    {{-- Tema antes de pintar, para que no parpadee: preferencia guardada o la del sistema. --}}
    <script>
        (() => {
            let t = 'auto';
            try { t = localStorage.getItem('tema') || 'auto'; } catch {}
            const dark = t === 'dark' || (t === 'auto' && matchMedia('(prefers-color-scheme: dark)').matches);
            document.documentElement.classList.toggle('dark', dark);
            document.documentElement.dataset.tema = t;
        })();
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flex min-h-full flex-col bg-stone-50 text-stone-900 antialiased dark:bg-stone-950 dark:text-stone-100">
    <header class="mx-auto w-full max-w-3xl px-4 pt-10 pb-6 sm:pt-14">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <a href="{{ route('home') }}" class="font-serif text-2xl font-bold tracking-tight hover:text-amber-700 dark:hover:text-amber-400">
                {{ config('app.name') }}
            </a>
            <div class="flex items-center gap-2">
            <form method="get" action="{{ route('home') }}" role="search">
                <input type="search" name="q" value="{{ $q ?? '' }}" placeholder="Buscar…" aria-label="Buscar en el blog"
                       class="w-40 rounded-full border border-stone-300 bg-white px-4 py-1.5 text-sm focus:w-52 focus:border-amber-500 focus:outline-none focus:ring-2 focus:ring-amber-500/30 transition-all dark:border-stone-700 dark:bg-stone-900">
            </form>
            <button type="button" data-theme-toggle
                    class="flex size-9 items-center justify-center rounded-full border border-stone-300 text-stone-600 hover:border-amber-500 hover:text-amber-700 dark:border-stone-700 dark:text-stone-300 dark:hover:text-amber-400"
                    aria-label="Cambiar tema" title="Tema: automático">
                <svg data-icon="auto" viewBox="0 0 24 24" class="size-4" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><path d="M12 3a9 9 0 0 0 0 18z" fill="currentColor"/></svg>
                <svg data-icon="light" viewBox="0 0 24 24" class="hidden size-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></svg>
                <svg data-icon="dark" viewBox="0 0 24 24" class="hidden size-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z"/></svg>
            </button>
            </div>
        </div>
        @if (config('app.blog_description'))
            <p class="mt-2 text-stone-500 dark:text-stone-400">{{ config('app.blog_description') }}</p>
        @endif
    </header>

    <main class="mx-auto w-full max-w-3xl flex-1 px-4 pb-16">
        @yield('content')
    </main>

    <footer class="border-t border-stone-200 dark:border-stone-800">
        <div class="mx-auto flex max-w-3xl justify-between px-4 py-6 text-sm text-stone-500">
            <span>© {{ date('Y') }} {{ config('app.name') }}</span>
            <a href="{{ route('feed') }}" class="hover:text-amber-700 dark:hover:text-amber-400">RSS</a>
        </div>
    </footer>
</body>
</html>
