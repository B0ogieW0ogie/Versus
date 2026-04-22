<div class="pb-6 lg:grid lg:grid-cols-[minmax(0,1fr)_320px] lg:gap-8 lg:max-w-7xl lg:mx-auto lg:px-6">
    <div class="min-w-0">
        @if ($featured)
            <div class="max-w-xl mx-auto lg:max-w-none">
                @include('livewire.battle-index.featured-card', ['featured' => $featured])
            </div>
        @endif

        <div class="max-w-xl mx-auto lg:max-w-none">
            @include('livewire.battle-index.hot-rail', ['hot' => $hot])

            @include('livewire.battle-index.category-chips', [
                'categories' => $categories,
                'category' => $category,
                'finished' => $finished,
            ])

            @include('livewire.battle-index.all-list', [
                'all' => $all,
                'category' => $category,
                'finished' => $finished,
            ])
        </div>
    </div>

    <aside class="hidden lg:block">
        <livewire:sidebar-widgets />
    </aside>
</div>
