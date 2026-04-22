@php /** @var \App\Models\Battle $featured */ @endphp

<section class="mx-3 rounded-2xl border border-white/5 bg-white/[0.04] p-4 sm:p-5">
    <div class="text-[11px] uppercase tracking-wider text-white/55 mb-3">
        {{ __('battle.featured') }}
    </div>
    <div class="text-center mb-2">
        <h2 class="text-lg font-semibold text-white">{{ $featured->title }}</h2>
    </div>

    <livewire:battle-vote-widget :battle="$featured" :key="'featured-'.$featured->id" />
</section>
