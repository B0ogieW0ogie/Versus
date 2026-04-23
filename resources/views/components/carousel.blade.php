@props([
    'perPageMobile' => 2,
    'perPageDesktop' => 4,
    'autoAdvance' => false,
    'intervalMs' => 6000,
    'showArrows' => true,
    'showDots' => true,
])

<div
    {{ $attributes->merge(['class' => 'relative']) }}
    x-data="carousel({
        perPageMobile: {{ (int) $perPageMobile }},
        perPageDesktop: {{ (int) $perPageDesktop }},
        autoAdvance: {{ $autoAdvance ? 'true' : 'false' }},
        intervalMs: {{ (int) $intervalMs }},
    })"
    x-init="
        total = $refs.track.children.length;
        Array.from($refs.track.children).forEach((el) => { el.style.flex = '0 0 auto'; });
        $watch('perPage', (p) => {
            Array.from($refs.track.children).forEach((el) => { el.style.width = (100 / p) + '%'; });
        });
        Array.from($refs.track.children).forEach((el) => { el.style.width = (100 / perPage) + '%'; });
        init();
    "
    x-on:mouseenter="onPause()"
    x-on:mouseleave="onResume()"
    x-on:focusin="onPause()"
    x-on:focusout="onResume()"
    x-on:touchstart.passive="onTouchStart($event)"
    x-on:touchend.passive="onTouchEnd($event)"
>
    <div class="overflow-hidden">
        <div class="flex transition-transform duration-300 ease-out" x-ref="track" :style="trackStyle">
            {{ $slot }}
        </div>
    </div>

    @if ($showArrows)
        <button type="button"
                x-show="pageCount > 1"
                x-on:click="prev()"
                :disabled="isFirst"
                class="absolute left-1 top-1/2 -translate-y-1/2 z-10 h-8 w-8 rounded-full bg-navy-900/70 text-white/80 hover:text-white border border-white/10 disabled:opacity-30 disabled:cursor-not-allowed flex items-center justify-center"
                aria-label="Previous">‹</button>
        <button type="button"
                x-show="pageCount > 1"
                x-on:click="next()"
                :disabled="isLast"
                class="absolute right-1 top-1/2 -translate-y-1/2 z-10 h-8 w-8 rounded-full bg-navy-900/70 text-white/80 hover:text-white border border-white/10 disabled:opacity-30 disabled:cursor-not-allowed flex items-center justify-center"
                aria-label="Next">›</button>
    @endif

    @if ($showDots)
        <div x-show="pageCount > 1" class="mt-3 flex justify-center gap-1.5">
            <template x-for="i in pageCount" :key="i">
                <button type="button"
                        x-on:click="goTo(i - 1)"
                        :class="page === (i - 1) ? 'bg-white' : 'bg-white/25 hover:bg-white/50'"
                        class="h-1.5 w-1.5 rounded-full transition-colors"
                        :aria-label="'Go to page ' + i"></button>
            </template>
        </div>
    @endif
</div>
