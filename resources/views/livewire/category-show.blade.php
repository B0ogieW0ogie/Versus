<div class="pb-6 lg:grid lg:grid-cols-[minmax(0,1fr)_320px] lg:gap-8 lg:max-w-7xl lg:mx-auto lg:px-6">
    <div class="min-w-0">
        <header class="px-3 mb-4">
            <h1 class="text-xl font-semibold text-white">{{ $category->localized_name }}</h1>
            <p class="text-xs text-white/55 mt-1">
                {{ trans_choice(':count active battles', $battles->total(), ['count' => $battles->total()]) }}
            </p>
        </header>

        @if ($battles->total() === 0)
            <div class="mx-3 rounded-xl border border-dashed border-white/10 p-8 text-center text-sm text-white/55">
                {{ __('battle.no_active_in_category') }}
                <div class="mt-3">
                    <a href="{{ route('home') }}" class="text-glow-cyan hover:underline">← {{ __('nav.home') }}</a>
                </div>
            </div>
        @else
            <div class="grid grid-cols-2 gap-3 px-3 lg:grid-cols-4">
                @foreach ($battles as $battle)
                    <x-battle-tile :battle="$battle" />
                @endforeach
            </div>

            @if ($battles->hasMorePages())
                <div class="px-3 mt-4">
                    <button type="button" wire:click="nextPage"
                            class="w-full rounded-xl border border-dashed border-white/10 py-3 text-sm text-white/60 hover:text-white">
                        {{ __('battle.load_more') }}
                    </button>
                </div>
            @endif
        @endif
    </div>

    <aside class="hidden lg:block">
        <livewire:sidebar-widgets />
    </aside>
</div>
