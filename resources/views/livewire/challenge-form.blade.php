<div x-data="challengeUpload({
        maxSeconds: @js($maxSeconds),
        maxBytes: @js($maxBytes),
        urls: @js([
            'start' => route('challenge-uploads.start'),
            'chunk' => route('challenge-uploads.chunk', '__ID__'),
            'status' => route('challenge-uploads.status', '__ID__'),
            'complete' => route('challenge-uploads.complete', '__ID__'),
        ]),
        backUrl: @js($backUrl),
        i18n: @js([
            'tooLong' => __('challenges.video_too_long', ['seconds' => $maxSeconds]),
            'tooBig' => __('challenges.video_too_big', ['mb' => config('versus.challenges.max_upload_mb')]),
            'failed' => __('challenges.upload_failed'),
        ]),
     })"
     @input="dirty = true"
     class="min-h-screen bg-navy-900 px-4 pb-10 pt-4 text-white">

    <header class="mb-6 flex items-center gap-4">
        <button type="button" class="p-2 text-2xl leading-none" @click="back()" aria-label="{{ __('challenges.leave') }}">‹</button>
        <h1 class="text-2xl font-bold">{{ $isResponse ? __('challenges.respond_title') : __('challenges.create_title') }}</h1>
    </header>

    {{-- Video --}}
    <div class="overflow-hidden rounded-2xl border-2 border-dashed border-indigo-500/70">
        <div class="relative flex aspect-[9/12] items-center justify-center bg-white/5" @click="pick()">
            <video x-show="previewUrl" :src="previewUrl" class="absolute inset-0 h-full w-full object-contain" playsinline muted loop autoplay></video>
            <div x-show="!previewUrl" class="text-center text-white/70">
                <div class="mb-3 text-4xl text-indigo-400">🎬</div>
                <p>{{ __('challenges.upload_hint') }}</p>
            </div>
            <div x-show="state === 'uploading'" class="absolute inset-x-0 bottom-0 bg-black/70 px-4 py-2 text-sm"
                 x-text="@js(__('challenges.uploading', ['percent' => '__P__'])).replace('__P__', progress)"></div>
        </div>
        <button type="button" class="flex w-full items-center justify-center gap-2 border-t border-dashed border-indigo-500/70 py-3 font-semibold" @click="pick()">
            ⬆ {{ __('challenges.upload') }}
        </button>
        <input x-ref="file" type="file" accept="video/*" class="hidden" @change="onFile($event)">
    </div>
    <p x-show="error" x-text="error" class="mt-2 text-sm text-rose-400"></p>
    @error('uploadId') <p class="mt-2 text-sm text-rose-400">{{ $message }}</p> @enderror
    @error('upload') <p class="mt-2 text-sm text-rose-400">{{ $message }}</p> @enderror
    @error('challenge') <p class="mt-2 text-sm text-rose-400">{{ $message }}</p> @enderror

    {{-- Fields --}}
    <div class="mt-6 space-y-5">
        <label class="block">
            <span class="mb-2 block">{{ __('challenges.field_title') }} *</span>
            <input type="text" maxlength="100" wire:model="title" @disabled($isResponse)
                   placeholder="{{ __('challenges.field_title_placeholder') }}"
                   class="w-full rounded-xl border-white/10 bg-white/5 text-white disabled:opacity-70">
            <span class="mt-1 block text-right text-xs text-white/50" x-text="($wire.title || '').length + '/100'"></span>
            @error('title') <span class="text-sm text-rose-400">{{ $message }}</span> @enderror
        </label>

        <label class="block">
            <span class="mb-2 block">{{ __('challenges.field_rules') }} *</span>
            <textarea rows="4" maxlength="500" wire:model="rules" @disabled($isResponse)
                      placeholder="{{ __('challenges.field_rules_placeholder') }}"
                      class="w-full rounded-xl border-white/10 bg-white/5 text-white disabled:opacity-70"></textarea>
            <span class="mt-1 block text-right text-xs text-white/50" x-text="($wire.rules || '').length + '/500'"></span>
            @error('rules') <span class="text-sm text-rose-400">{{ $message }}</span> @enderror
        </label>

        @if ($isResponse)
            <div class="rounded-xl border border-white/10 bg-white/5 px-4 py-3">
                <span class="text-white/60">{{ __('challenges.time_left') }}:</span>
                <span x-data="countdown(@js($challenge->ends_at?->toIso8601String()))" x-init="start()" x-text="label" class="font-semibold"></span>
            </div>
        @else
            <div x-data="{ open: false }">
                <span class="mb-2 block">{{ __('challenges.field_deadline') }}</span>
                <button type="button" @click="open = true" class="flex w-full items-center justify-between rounded-xl border border-white/10 bg-white/5 px-4 py-3">
                    <span>🕒 {{ $duration ? __('challenges.duration_'.$duration) : __('challenges.select_time') }}</span>
                    <span>›</span>
                </button>
                @error('duration') <span class="text-sm text-rose-400">{{ $message }}</span> @enderror
                <div x-show="open" x-cloak class="fixed inset-0 z-50 flex items-end bg-black/60" @click.self="open = false">
                    <div class="w-full space-y-2 rounded-t-3xl bg-navy-900 p-5">
                        @foreach ($durations as $option)
                            <button type="button" class="w-full rounded-xl px-4 py-3 text-left {{ $duration === $option ? 'bg-indigo-600' : 'bg-white/5' }}"
                                    wire:click="$set('duration', '{{ $option }}')" @click="open = false; dirty = true">
                                {{ __('challenges.duration_'.$option) }}
                            </button>
                        @endforeach
                    </div>
                </div>
            </div>
        @endif

        @if ($needsUsername)
            <label class="block">
                <span class="mb-2 block">{{ __('challenges.field_username') }}</span>
                <input type="text" maxlength="32" wire:model="username" class="w-full rounded-xl border-white/10 bg-white/5 text-white" placeholder="@username">
                @error('username') <span class="text-sm text-rose-400">{{ $message }}</span> @enderror
            </label>
        @endif
        @error('daily_limit') <p class="text-sm text-rose-400">{{ $message }}</p> @enderror
    </div>

    <button type="button"
            class="mt-8 flex h-14 w-full items-center justify-center rounded-2xl bg-indigo-600 font-semibold uppercase tracking-wide disabled:opacity-50"
            :disabled="state === 'uploading' || submitting"
            @click="submitting = true; $wire.publish().finally(() => { submitting = false })">
        {{ $isResponse ? __('challenges.publish_response') : __('challenges.publish') }}
    </button>

    {{-- Leave confirmation --}}
    <div x-show="confirmLeave" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 px-6">
        <div class="w-full max-w-sm space-y-4 rounded-2xl bg-navy-900 p-6 text-center">
            <p class="text-lg font-semibold">{{ __('challenges.leave_confirm') }}</p>
            <div class="flex gap-3">
                <button type="button" class="flex-1 rounded-xl bg-white/10 py-3" @click="confirmLeave = false">{{ __('challenges.stay') }}</button>
                <button type="button" class="flex-1 rounded-xl bg-rose-600 py-3" @click="leave()">{{ __('challenges.leave') }}</button>
            </div>
        </div>
    </div>
</div>
