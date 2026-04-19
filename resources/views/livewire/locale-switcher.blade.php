<div class="inline-flex items-center rounded-md border border-gray-200 dark:border-gray-600 text-xs overflow-hidden">
    @foreach ($supported as $locale)
        <button
            type="button"
            wire:click="switch('{{ $locale }}')"
            class="px-2 py-1 uppercase tracking-wider transition
                {{ $current === $locale
                    ? 'bg-gray-800 text-white dark:bg-gray-200 dark:text-gray-900'
                    : 'bg-white text-gray-600 hover:bg-gray-100 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700' }}"
        >
            {{ $locale }}
        </button>
    @endforeach
</div>
