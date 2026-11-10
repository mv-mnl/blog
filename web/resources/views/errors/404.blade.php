@extends('layout')

@section('title', 'No encontrado')

@section('content')
    <div class="py-20 text-center">
        <p class="font-serif text-7xl font-bold text-amber-600">404</p>
        <p class="mt-4 text-lg text-stone-600 dark:text-stone-400">Esta página no existe o ya no está publicada.</p>
        <a href="{{ route('home') }}" class="mt-8 inline-block rounded-full border border-stone-300 px-5 py-2 text-sm hover:border-amber-500 dark:border-stone-700">Volver al inicio</a>
    </div>
@endsection
