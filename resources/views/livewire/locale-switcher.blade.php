<div class="inline-flex items-center rounded-md bg-white/5 text-xs overflow-hidden">
    @foreach ($supported as $locale)
        <button
            type="button"
            wire:click="switch('{{ $locale }}')"
            class="px-2.5 py-1 uppercase tracking-wider font-semibold transition
                {{ $current === $locale
                    ? 'bg-white text-navy-900'
                    : 'text-white/60 hover:text-white hover:bg-white/10' }}"
        >
            {{ $locale }}
        </button>
    @endforeach
</div>
