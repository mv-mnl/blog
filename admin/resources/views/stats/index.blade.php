@extends('layout')

@section('title', 'Estadísticas')

@section('content')
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <h1 class="text-2xl font-bold">Estadísticas</h1>
        <nav class="flex rounded-md border border-stone-300 p-0.5 text-sm dark:border-stone-700">
            @foreach ([7 => '7 días', 30 => '30 días', 90 => '90 días', 365 => '1 año'] as $d => $label)
                <a href="{{ route('stats.index', ['dias' => $d]) }}"
                   class="rounded px-3 py-1.5 {{ $days === $d ? 'bg-stone-200 font-medium dark:bg-stone-800' : 'text-stone-500 hover:text-stone-900 dark:hover:text-stone-100' }}">{{ $label }}</a>
            @endforeach
        </nav>
    </div>

    <div class="mb-6 grid gap-4 sm:grid-cols-3">
        @foreach ([['Visitas', $totalViews, 'páginas vistas'], ['Visitantes', $totalVisitors, 'personas distintas al día, sumadas'], ['Comentarios pendientes', $pending, 'esperando tu aprobación']] as [$label, $value, $hint])
            <div class="rounded-lg border border-stone-200 bg-white p-5 dark:border-stone-800 dark:bg-stone-900">
                <div class="text-sm text-stone-500">{{ $label }}</div>
                <div class="mt-1 text-3xl font-bold tabular-nums">{{ number_format($value, 0, ',', '.') }}</div>
                <div class="mt-1 text-xs text-stone-400">{{ $hint }}</div>
            </div>
        @endforeach
    </div>

    <div class="mb-6 rounded-lg border border-stone-200 bg-white p-5 dark:border-stone-800 dark:bg-stone-900">
        <h2 class="mb-4 text-sm font-semibold text-stone-500">Visitas por día</h2>
        <div class="h-72"><canvas data-stats-chart='@json($series)'></canvas></div>
    </div>

    <div class="grid gap-6 lg:grid-cols-2">
        <div class="rounded-lg border border-stone-200 bg-white p-5 dark:border-stone-800 dark:bg-stone-900">
            <h2 class="mb-3 text-sm font-semibold text-stone-500">Posts más leídos</h2>
            @forelse ($topPosts as $p)
                <div class="flex items-center justify-between gap-4 border-b border-stone-100 py-2 text-sm last:border-0 dark:border-stone-800">
                    <a href="{{ route('posts.edit', $p->id) }}" class="truncate hover:underline">{{ $p->title }}</a>
                    <span class="tabular-nums text-stone-500">{{ $p->views }}</span>
                </div>
            @empty
                <p class="text-sm text-stone-400">Sin datos todavía.</p>
            @endforelse
        </div>
        <div class="rounded-lg border border-stone-200 bg-white p-5 dark:border-stone-800 dark:bg-stone-900">
            <h2 class="mb-3 text-sm font-semibold text-stone-500">De dónde llegan</h2>
            @forelse ($referrers as $r)
                <div class="flex items-center justify-between gap-4 border-b border-stone-100 py-2 text-sm last:border-0 dark:border-stone-800">
                    <span class="truncate">{{ $r->referrer_host }}</span>
                    <span class="tabular-nums text-stone-500">{{ $r->views }}</span>
                </div>
            @empty
                <p class="text-sm text-stone-400">Nadie ha llegado desde otra web todavía (o entraron directamente).</p>
            @endforelse
        </div>
    </div>

    <p class="mt-6 text-xs text-stone-400">
        Sin cookies ni servicios externos: cada visitante se cuenta con un código anónimo que cambia cada día.
        No se guardan IPs. Se excluyen los bots conocidos.
    </p>
@endsection
