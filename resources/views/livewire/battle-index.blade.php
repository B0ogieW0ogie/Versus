<div class="pb-6 lg:grid lg:grid-cols-[minmax(0,1fr)_320px] lg:gap-8 lg:max-w-7xl lg:mx-auto lg:px-6">
    <div class="min-w-0 space-y-6">
        @if ($sponsored->isNotEmpty())
            <section class="px-1 sm:px-0">
                <x-sponsored-carousel>
                    @foreach ($sponsored as $battle)
                        <div class="w-[82%] shrink-0 sm:w-[72%] lg:w-[68%] transition-all duration-500 ease-out"
                             data-sponsor-label="{{ ($battle->is_sponsored && $battle->sponsor_handle) ? __('battle.sponsored_by', ['handle' => $battle->sponsor_handle]) : '' }}"
                             :class="slideClass({{ $loop->index }})">
                            <x-battle-featured-card :battle="$battle" />
                        </div>
                    @endforeach
                </x-sponsored-carousel>
            </section>
        @endif

        @if ($hot->isNotEmpty())
            <section>
                <header class="mb-3 flex items-baseline justify-between px-3">
                    <div class="text-[11px] uppercase tracking-wider text-white/55">
                        {{ __('battle.hot') }}
                    </div>
                </header>
                <div class="px-3">
                    <x-carousel :per-page-mobile="2" :per-page-md="3" :per-page-desktop="3">
                        @foreach ($hot as $battle)
                            <div class="h-full pr-2 sm:pr-3">
                                <x-battle-tile :battle="$battle" size="lg" />
                            </div>
                        @endforeach
                    </x-carousel>
                </div>
            </section>
        @endif

        @foreach ($categoryRails as $category)
            <section>
                <header class="flex items-baseline justify-between px-3 mb-2">
                    <div class="text-[11px] uppercase tracking-wider text-white/55">
                        {{ $category->localized_name }}
                    </div>
                    <a href="{{ route('categories.show', $category) }}"
                       class="text-[11px] text-glow-cyan hover:underline">
                        {{ __('battle.view_all') }} ›
                    </a>
                </header>
                <div class="px-3">
                    <x-carousel>
                        @foreach ($category->battles as $battle)
                            <div class="pr-2">
                                <x-battle-tile :battle="$battle" />
                            </div>
                        @endforeach
                    </x-carousel>
                </div>
            </section>
        @endforeach
    </div>

    <aside class="hidden lg:block">
        <livewire:sidebar-widgets />
    </aside>
</div>
