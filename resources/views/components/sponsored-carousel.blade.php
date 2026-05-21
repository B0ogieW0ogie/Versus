@props([
    'autoAdvance' => true,
    'intervalMs' => 6000,
    'loop' => false,
])

<div
    {{ $attributes->merge(['class' => 'relative']) }}
    x-data="sponsoredPeekCarousel({
        autoAdvance: {{ $autoAdvance ? 'true' : 'false' }},
        intervalMs: {{ (int) $intervalMs }},
        loop: {{ $loop ? 'true' : 'false' }},
    })"
    x-init="
        total = $refs.track.children.length;
        init();
    "
    x-on:mouseenter="onPause()"
    x-on:mouseleave="onResume()"
    x-on:focusin="onPause()"
    x-on:focusout="onResume()"
    x-on:touchstart.passive="onTouchStart($event)"
    x-on:touchend.passive="onTouchEnd($event)"
>
    <p x-show="sponsorLabel"
       x-text="sponsorLabel"
       x-cloak
       class="mb-3 text-center text-sm font-medium tracking-wide text-white/55"></p>

    <div class="relative overflow-hidden" x-ref="viewport">
        <div class="flex items-center gap-4 transition-transform duration-500 ease-out will-change-transform"
             x-ref="track"
             :style="trackStyle">
            {{ $slot }}
        </div>
    </div>

    <div x-show="pageCount > 1" class="mt-4 flex justify-center gap-1.5">
        <template x-for="i in pageCount" :key="i">
            <button type="button"
                    x-on:click="goTo(i - 1)"
                    :class="realPage === (i - 1) ? 'bg-white' : 'bg-white/25 hover:bg-white/50'"
                    class="h-1.5 w-1.5 rounded-full transition-colors"
                    :aria-label="'Go to slide ' + i"></button>
        </template>
    </div>
</div>
