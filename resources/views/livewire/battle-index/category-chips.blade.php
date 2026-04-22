@php
    /** @var \Illuminate\Support\Collection<int, \App\Models\Category> $categories */
@endphp

<nav class="mt-5 overflow-x-auto scrollbar-hide">
    <ul class="flex gap-2 px-3 min-w-max">
        <li>
            <button type="button"
                    wire:click="selectCategory(null)"
                    class="px-3 py-1.5 rounded-full border text-xs transition
                           {{ $category === null && ! $finished
                               ? 'bg-navy-800 border-white/30 text-white'
                               : 'border-white/10 text-white/70 hover:text-white' }}">
                {{ __('battle.all_chip') }}
            </button>
        </li>
        @foreach ($categories as $cat)
            <li>
                <button type="button"
                        wire:click="selectCategory('{{ $cat->slug }}')"
                        class="px-3 py-1.5 rounded-full border text-xs transition
                               {{ $category === $cat->slug
                                   ? 'bg-navy-800 border-white/30 text-white'
                                   : 'border-white/10 text-white/70 hover:text-white' }}">
                    {{ $cat->localized_name }}
                </button>
            </li>
        @endforeach
        <li>
            <button type="button"
                    wire:click="toggleFinished"
                    class="px-3 py-1.5 rounded-full border border-dashed text-xs transition
                           {{ $finished
                               ? 'bg-navy-800 border-white/40 text-white'
                               : 'border-white/20 text-white/60 hover:text-white' }}">
                {{ __('battle.finished_chip') }}
            </button>
        </li>
    </ul>
</nav>
