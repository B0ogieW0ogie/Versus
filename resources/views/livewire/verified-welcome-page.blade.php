@php
    /** @var array<int, array{heading: string, body: string}> $slides */
    $slides = __('welcome.slides');
@endphp

<div class="min-h-[calc(100vh-4rem)] flex flex-col bg-navy-900 px-4 pt-8 pb-28 sm:pb-12">
    <div class="max-w-md mx-auto w-full flex-1 flex flex-col">
        <h1 class="text-2xl sm:text-3xl font-bold text-white text-center leading-tight">
            {{ __('welcome.title') }}
        </h1>

        <div class="mt-10 flex-1 flex flex-col justify-center">
            <div class="rounded-2xl border border-white/10 bg-white/5 p-6 sm:p-8 min-h-[220px] flex flex-col">
                <h2 class="text-xl font-bold text-transparent bg-clip-text bg-gradient-to-r from-vote-blue-from to-vote-purple-to">
                    {{ $slides[$slide]['heading'] }}
                </h2>
                <p class="mt-4 text-sm sm:text-base text-white/75 leading-relaxed">
                    {{ $slides[$slide]['body'] }}
                </p>
            </div>

            <div class="flex justify-center gap-2 mt-8" role="tablist" aria-label="Slides">
                @foreach (range(0, 2) as $i)
                    <span
                        class="h-2 rounded-full transition-all duration-300 {{ $slide === $i ? 'w-8 bg-vote-purple-to' : 'w-2 bg-white/25' }}"
                        @if ($slide === $i) aria-current="true" @endif
                    ></span>
                @endforeach
            </div>
        </div>

        <div class="mt-8 flex flex-col-reverse sm:flex-row gap-3 sm:justify-between sm:items-center">
            <div class="flex gap-2 justify-center sm:justify-start">
                @if ($slide > 0)
                    <button type="button" wire:click="prev"
                            class="px-5 py-2.5 rounded-xl border border-white/15 text-sm font-semibold text-white/80 hover:bg-white/5 transition">
                        {{ __('welcome.back') }}
                    </button>
                @endif
            </div>

            <div class="flex justify-center sm:justify-end">
                @if ($slide < 2)
                    <button type="button" wire:click="next"
                            class="w-full sm:w-auto min-w-[200px] px-6 py-3 rounded-xl text-sm font-bold text-white bg-gradient-to-r from-vote-blue-from to-vote-purple-to shadow-vote-blue hover:opacity-95 transition">
                        {{ __('welcome.next') }}
                    </button>
                @else
                    <button type="button" wire:click="start"
                            class="w-full sm:w-auto min-w-[200px] px-6 py-3 rounded-xl text-sm font-bold text-white bg-gradient-to-r from-vote-blue-from to-vote-purple-to shadow-vote-blue hover:opacity-95 transition">
                        {{ __('welcome.start') }}
                    </button>
                @endif
            </div>
        </div>
    </div>
</div>
