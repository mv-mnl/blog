@if ($tags->isNotEmpty())
    <ul class="mt-4 flex flex-wrap gap-2">
        @foreach ($tags as $t)
            <li>
                <a href="{{ route('tags.show', $t->slug) }}"
                   class="rounded-full bg-stone-200/70 px-3 py-1 text-xs font-medium text-stone-700 hover:bg-amber-100 hover:text-amber-900 dark:bg-stone-800 dark:text-stone-300 dark:hover:bg-amber-950 dark:hover:text-amber-300">#{{ $t->name }}</a>
            </li>
        @endforeach
    </ul>
@endif
