{{-- The VERSUS desktop frame (lg+): recommendations · page · side navigation. Below lg only the page renders. --}}
<div {{ $attributes->merge(['class' => 'min-h-screen bg-navy-900 text-white lg:grid lg:grid-cols-[minmax(15rem,20rem)_minmax(0,1fr)_16rem] lg:gap-8 lg:px-8']) }}>
    <aside class="hidden lg:block">
        <div class="sticky top-0 py-6">
            @include('challenges.may-like')
        </div>
    </aside>

    <div class="min-w-0">
        {{ $slot }}
    </div>

    <div class="hidden lg:block">
        <div class="sticky top-0 flex h-screen flex-col [&>nav]:flex-1">
            @include('layouts.side-nav')
        </div>
    </div>
</div>
