{{-- Portada: clic o arrastrar una imagen. $name, $removeName, $url, $small --}}
<div data-cover class="group relative">
    <label class="relative flex cursor-pointer items-center justify-center overflow-hidden rounded-xl border-2 border-dashed border-stone-300 bg-stone-50 text-stone-500 transition hover:border-amber-400 hover:bg-amber-50/50 data-[filled]:border-0 data-[dragging]:border-amber-500 data-[dragging]:bg-amber-50 dark:border-stone-700 dark:bg-stone-900 dark:hover:bg-amber-950/20 {{ $small ?? false ? 'h-28 data-[filled]:h-52' : 'h-32 data-[filled]:h-80' }}"
           @if ($url) data-filled @endif data-cover-zone>
        <input type="file" name="{{ $name }}" accept="image/jpeg,image/png,image/webp,image/gif" class="sr-only" data-cover-input>
        <img src="{{ $url }}" alt="" data-cover-img class="absolute inset-0 size-full object-cover {{ $url ? '' : 'hidden' }}">
        <span data-cover-empty class="pointer-events-none text-center text-sm {{ $url ? 'hidden' : '' }}">
            <svg viewBox="0 0 24 24" class="mx-auto mb-1 size-6" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-3.1-3.1a2 2 0 0 0-2.8 0L6 21"/></svg><span class="font-medium">Añadir imagen de portada</span> · haz clic o arrástrala aquí
            <span class="block text-center text-xs text-stone-400">opcional · JPG, PNG, WebP o GIF, máx. 5 MB</span>
        </span>
    </label>
    <div data-cover-actions class="absolute top-3 right-3 flex gap-2 opacity-0 transition group-hover:opacity-100 {{ $url ? '' : 'hidden' }}">
        <button type="button" data-cover-change class="rounded-md bg-white/90 px-3 py-1.5 text-xs font-medium text-stone-800 shadow hover:bg-white">Cambiar</button>
        <button type="button" data-cover-remove class="rounded-md bg-white/90 px-3 py-1.5 text-xs font-medium text-red-700 shadow hover:bg-white">Quitar</button>
    </div>
    <input type="hidden" name="{{ $removeName }}" value="0" data-cover-removed>
</div>
