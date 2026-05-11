@php
    /** @var array<int, array{heading: string, body: string}> $slides */
    $slides = __('welcome.slides');
@endphp

<div>
@if ($open)
    <div class="fixed inset-0 z-[60] flex items-end sm:items-center justify-center p-4 sm:p-6"
         role="dialog"
         aria-modal="true"
         aria-labelledby="verified-welcome-title">
        {{-- Backdrop: главная страница остаётся видимой --}}
        <div class="absolute inset-0 bg-navy-900/45 backdrop-blur-[3px]" aria-hidden="true"></div>

        <div class="relative w-full max-w-md rounded-2xl border border-white/15 bg-navy-900 shadow-2xl shadow-black/40 max-h-[min(90vh,640px)] flex flex-col">
            <div class="overflow-y-auto p-5 sm:p-7 flex flex-col">
                <h1 id="verified-welcome-title" class="text-xl sm:text-2xl font-bold text-white text-center leading-tight">
                    {{ __('welcome.title') }}
                </h1>

                <div class="mt-6">
                    <div class="rounded-xl border border-white/10 bg-white/5 p-5 sm:p-6 min-h-[180px] flex flex-col">
                        <h2 class="text-lg font-bold text-transparent bg-clip-text bg-gradient-to-r from-vote-blue-from to-vote-purple-to">
                            {{ $slides[$slide]['heading'] }}
                        </h2>
                        <p class="mt-3 text-sm text-white/75 leading-relaxed">
                            {{ $slides[$slide]['body'] }}
                        </p>
                    </div>

                    <div class="flex justify-center gap-2 mt-5" role="tablist" aria-label="Slides">
                        @foreach (range(0, 2) as $i)
                            <span
                                class="h-2 rounded-full transition-all duration-300 {{ $slide === $i ? 'w-8 bg-vote-purple-to' : 'w-2 bg-white/25' }}"
                                @if ($slide === $i) aria-current="true" @endif
                            ></span>
                        @endforeach
                    </div>
                </div>

                <div class="mt-6 flex flex-col-reverse sm:flex-row gap-3 sm:justify-between sm:items-center">
                    <div class="flex gap-2 justify-center sm:justify-start">
                        @if ($slide > 0)
                            <button type="button" wire:click="prev"
                                    class="px-4 py-2.5 rounded-xl border border-white/15 text-sm font-semibold text-white/80 hover:bg-white/5 transition">
                                {{ __('welcome.back') }}
                            </button>
                        @endif
                    </div>

                    <div class="flex justify-center sm:justify-end w-full sm:w-auto">
                        @if ($slide < 2)
                            <button type="button" wire:click="next"
                                    class="w-full sm:w-auto min-w-[180px] px-6 py-3 rounded-xl text-sm font-bold text-white bg-gradient-to-r from-vote-blue-from to-vote-purple-to shadow-vote-blue hover:opacity-95 transition">
                                {{ __('welcome.next') }}
                            </button>
                        @else
                            <button type="button" wire:click="start"
                                    class="w-full sm:w-auto min-w-[180px] px-6 py-3 rounded-xl text-sm font-bold text-white bg-gradient-to-r from-vote-blue-from to-vote-purple-to shadow-vote-blue hover:opacity-95 transition">
                                {{ __('welcome.start') }}
                            </button>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
@endif
</div>
