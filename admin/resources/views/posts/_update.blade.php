{{-- Tarjeta de una actualización (mini-post). $row: key, id, title, body, cover, date, delete --}}
@php $k = $row['key']; @endphp
<li class="relative" data-update-card @if ($row['delete']) data-deleted @endif>
    <span class="absolute top-6 -left-[41px] size-4 rounded-full border-[3px] border-amber-400 bg-stone-100 dark:bg-stone-950" aria-hidden="true"></span>

    <div class="rounded-xl border border-stone-200 bg-white p-5 shadow-sm transition dark:border-stone-800 dark:bg-stone-900" data-update-body>
        <div class="mb-4 flex items-center justify-between gap-4 text-sm">
            @if ($row['date'])
                <time class="font-semibold text-amber-700 dark:text-amber-400">{{ $row['date'] }}</time>
            @else
                <span class="font-semibold text-amber-700 dark:text-amber-400">Nueva · se añade con la fecha de hoy al guardar</span>
            @endif
            <button type="button" data-update-delete class="text-stone-500 hover:text-red-600 dark:hover:text-red-400">
                {{ $row['id'] ? 'Eliminar' : 'Descartar' }}
            </button>
        </div>

        <input type="hidden" name="updates[{{ $k }}][id]" value="{{ $row['id'] }}">
        <input type="hidden" name="updates[{{ $k }}][delete]" value="{{ $row['delete'] ? 1 : 0 }}" data-delete-input>

        <input name="updates[{{ $k }}][title]" value="{{ $row['title'] }}" maxlength="200"
               placeholder="Título de la actualización"
               class="mb-4 w-full border-0 bg-transparent p-0 font-serif text-2xl font-bold placeholder:text-stone-300 focus:ring-0 focus:outline-none dark:placeholder:text-stone-600">
        @error("updates.$k.title") <p class="-mt-2 mb-3 text-sm text-red-600">{{ $message }}</p> @enderror

        <div class="mb-4">
            @include('posts._cover', ['name' => "updates[$k][cover]", 'removeName' => "updates[$k][remove_cover]", 'url' => $row['cover'], 'small' => true])
        </div>

        @include('posts._editor', ['name' => "updates[$k][body]", 'value' => $row['body'], 'placeholder' => '¿Qué piensas ahora? ¿Qué cambió?', 'small' => true])
        @error("updates.$k.body") <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
    </div>

    <div class="hidden rounded-xl border border-dashed border-red-300 px-5 py-4 text-sm text-red-700 dark:border-red-900 dark:text-red-400" data-update-deleted-note>
        «{{ $row['title'] ?: 'Actualización' }}» se eliminará al guardar.
        <button type="button" data-update-undo class="ml-2 font-medium underline">Deshacer</button>
    </div>
</li>
