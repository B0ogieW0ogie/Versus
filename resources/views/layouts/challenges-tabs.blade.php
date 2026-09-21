{{-- Tabs inside the Challenges section: the video feed and, for signed-in users, My Challenges. --}}
@php
    $tabs = [
        ['route' => 'challenges.index', 'match' => ['challenges.index', 'challenges.show'], 'label' => __('challenges.tab_feed')],
        ['route' => 'challenges.mine', 'match' => ['challenges.mine'], 'label' => __('challenges.nav_my'), 'auth' => true],
    ];
@endphp
<nav data-challenges-tabs class="inline-flex rounded-2xl border border-white/10 bg-white/[0.03] p-1 text-sm font-semibold">
    @foreach ($tabs as $tab)
        @php $isActive = request()->routeIs(...$tab['match']); @endphp
        <a href="{{ ! empty($tab['auth']) && auth()->guest() ? route('login') : route($tab['route']) }}"
           @if ($isActive) aria-current="page" @endif
           class="rounded-xl px-4 py-2 transition {{ $isActive ? 'bg-indigo-600 text-white' : 'text-white/60 hover:text-white' }}">
            {{ $tab['label'] }}
        </a>
    @endforeach
</nav>
