@php
    $categoryIcons = [
        'sports' => '🏋️', 'music' => '🎵', 'gaming' => '🎮', 'creativity' => '🎨',
        'skills' => '🎓', 'lifestyle' => '😊', 'other' => '•••',
    ];
    $field = 'w-full rounded-xl border-white/10 bg-white/5 text-white placeholder-white/40 focus:border-indigo-500 focus:ring-indigo-500 disabled:opacity-70';
@endphp

<div class="min-h-screen bg-navy-900 text-white">
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
            'cameraDenied' => __('challenges.camera_denied'),
            'secondsShort' => __('challenges.seconds_short'),
        ]),
     })"
     @input="dirty = true"
     class="mx-auto max-w-5xl px-4 pb-10 pt-4 lg:px-0 lg:py-6">

    <header class="mb-6 flex items-start gap-3">
        <button type="button" class="-ml-2 p-2 text-2xl leading-none text-white/80 hover:text-white" @click="back()" aria-label="{{ __('challenges.leave') }}">←</button>
        <div>
            <h1 class="text-2xl font-bold lg:text-3xl">{{ $isResponse ? __('challenges.response_title') : __('challenges.create_title') }}</h1>
            @unless ($isResponse)
                <p class="mt-1 text-sm text-white/60">{{ __('challenges.create_subtitle') }}</p>
            @endunless
        </div>
    </header>

    <div class="flex flex-col gap-8 md:flex-row md:items-start">
        {{-- Video. Empty: a compact drop zone with Record | Upload. Picked: a 9:16 preview plus a trimmer. --}}
        <section data-video-zone class="w-full shrink-0 md:sticky md:top-6 md:w-64">
            <div data-video-empty x-show="!previewUrl && !camera"
                 class="flex flex-col overflow-hidden rounded-2xl border-2 border-dashed border-indigo-500/60 bg-indigo-500/[0.04] md:aspect-[9/16]">
                <button type="button" @click="pick()" class="flex h-40 w-full flex-col items-center justify-center gap-2 px-4 text-center hover:bg-white/[0.03] md:h-auto md:flex-1">
                    <svg class="h-12 w-12 text-indigo-400" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24" aria-hidden="true"><rect x="2.5" y="6" width="13" height="12" rx="2.5" /><path stroke-linejoin="round" d="m15.5 10.5 5-3v9l-5-3" /></svg>
                    <span class="text-sm font-medium text-white/85">{{ __('challenges.upload_hint') }}</span>
                    <span class="text-xs text-white/45">{{ __('challenges.upload_formats', ['seconds' => $maxSeconds, 'mb' => config('versus.challenges.max_upload_mb')]) }}</span>
                </button>
                <div class="grid grid-cols-2 border-t-2 border-dashed border-indigo-500/60 text-sm font-semibold">
                    <button type="button" data-record @click="openCamera()" class="flex h-12 items-center justify-center gap-2 border-r-2 border-dashed border-indigo-500/60 hover:bg-white/[0.04]">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><rect x="2.5" y="6" width="13" height="12" rx="2.5" /><path stroke-linejoin="round" d="m15.5 10.5 5-3v9l-5-3" /></svg>
                        {{ __('challenges.record') }}
                    </button>
                    <button type="button" data-upload @click="pick()" class="flex h-12 items-center justify-center gap-2 hover:bg-white/[0.04]">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 16V4m0 0-4 4m4-4 4 4M4 16v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2" /></svg>
                        {{ __('challenges.upload') }}
                    </button>
                </div>
            </div>
            <p x-show="!previewUrl && !camera" class="mt-2 flex items-start gap-2 text-xs text-indigo-200/80">
                <span aria-hidden="true">ⓘ</span> {{ __('challenges.trim_hint_before', ['seconds' => $maxSeconds]) }}
            </p>

            {{-- Vertical preview: the live camera while recording, the picked video otherwise --}}
            <div data-video-preview x-show="previewUrl || camera" x-cloak
                 class="relative mx-auto aspect-[9/16] h-[60vh] max-w-full overflow-hidden rounded-2xl border border-white/10 bg-black md:h-auto md:w-full">
                <video x-ref="live" x-show="camera" class="absolute inset-0 h-full w-full -scale-x-100 object-cover" playsinline muted autoplay></video>
                <video x-ref="preview" x-show="previewUrl && !camera" :src="previewUrl" class="absolute inset-0 h-full w-full object-contain"
                       playsinline muted loop autoplay @timeupdate="keepInTrim($event.target)"></video>

                <span x-show="recording" class="absolute left-3 top-3 flex items-center gap-2 rounded-full bg-black/60 px-3 py-1 text-xs font-semibold">
                    <span class="h-2 w-2 animate-pulse rounded-full bg-rose-500"></span>
                    <span x-text="recordLabel()"></span>
                </span>

                <div x-show="state === 'uploading'" class="absolute inset-x-0 bottom-0 bg-black/70 px-4 py-2 text-sm"
                     x-text="@js(__('challenges.uploading', ['percent' => '__P__'])).replace('__P__', progress)"></div>
                <div x-show="state === 'done' && !camera" class="absolute inset-x-0 bottom-0 bg-emerald-600/80 px-4 py-2 text-center text-sm font-semibold">
                    ✓ {{ __('challenges.upload_done') }}
                </div>
            </div>

            {{-- Trimmer: drag the edges to keep up to the time limit --}}
            <div data-trimmer x-show="hasTrimmer()" x-cloak class="mt-3">
                <div class="mb-1.5 flex items-center justify-between text-xs">
                    <span class="font-semibold">{{ __('challenges.trim') }}</span>
                    <span class="text-white/60" x-text="trimLabel()"></span>
                </div>
                <div x-ref="trimTrack" class="relative h-11 touch-none select-none rounded-lg bg-white/[0.06]">
                    <div class="absolute inset-y-0 rounded-md border-y-2 border-indigo-500 bg-indigo-500/20"
                         :style="`left: ${trimPercent(trimStart)}%; width: ${trimPercent(trimEnd - trimStart)}%`"></div>
                    <button type="button" aria-label="{{ __('challenges.trim_start') }}" @pointerdown.prevent="startTrimDrag($event, 'start')"
                            class="absolute inset-y-0 flex w-4 -translate-x-1/2 cursor-ew-resize items-center justify-center rounded-md bg-indigo-500"
                            :style="`left: ${trimPercent(trimStart)}%`"><span class="h-4 w-0.5 rounded bg-white"></span></button>
                    <button type="button" aria-label="{{ __('challenges.trim_end') }}" @pointerdown.prevent="startTrimDrag($event, 'end')"
                            class="absolute inset-y-0 flex w-4 -translate-x-1/2 cursor-ew-resize items-center justify-center rounded-md bg-indigo-500"
                            :style="`left: ${trimPercent(trimEnd)}%`"><span class="h-4 w-0.5 rounded bg-white"></span></button>
                </div>
                <p class="mt-1.5 text-xs text-white/45">{{ __('challenges.trim_hint') }}</p>
            </div>

            {{-- Actions once a video is picked or the camera is on --}}
            <div x-show="previewUrl || camera" x-cloak class="mt-3 grid grid-cols-2 gap-2 text-sm font-semibold">
                <template x-if="!camera">
                    <button type="button" data-record @click="openCamera()" :disabled="state === 'uploading'"
                            class="flex h-11 items-center justify-center gap-2 rounded-xl border border-indigo-500/50 bg-indigo-500/10 hover:bg-indigo-500/20 disabled:opacity-50">
                        <span class="h-2.5 w-2.5 rounded-full bg-rose-500"></span> {{ __('challenges.record') }}
                    </button>
                </template>
                <template x-if="camera && !recording">
                    <button type="button" @click="startRecording()" class="flex h-11 items-center justify-center gap-2 rounded-xl bg-rose-600 hover:bg-rose-500">
                        <span class="h-2.5 w-2.5 rounded-full bg-white"></span> {{ __('challenges.record_start') }}
                    </button>
                </template>
                <template x-if="recording">
                    <button type="button" @click="stopRecording()" class="flex h-11 items-center justify-center gap-2 rounded-xl bg-rose-600 hover:bg-rose-500">
                        <span class="h-2.5 w-2.5 rounded-sm bg-white"></span> {{ __('challenges.record_stop') }}
                    </button>
                </template>
                <template x-if="!camera">
                    <button type="button" @click="pick()" :disabled="state === 'uploading'"
                            class="flex h-11 items-center justify-center gap-2 rounded-xl border border-indigo-500/50 bg-indigo-500/10 hover:bg-indigo-500/20 disabled:opacity-50">
                        {{ __('challenges.replace_video') }}
                    </button>
                </template>
                <template x-if="camera">
                    <button type="button" @click="closeCamera()" class="flex h-11 items-center justify-center rounded-xl border border-white/15 hover:bg-white/5">
                        {{ __('challenges.record_cancel') }}
                    </button>
                </template>
            </div>

            <input x-ref="file" type="file" accept="video/*" class="hidden" @change="onFile($event)">
            {{-- Phones open the native camera from this input; desktops record in-page (see openCamera()). --}}
            <input x-ref="capture" type="file" accept="video/*" capture="user" class="hidden" @change="onFile($event)">

            <p x-show="error" x-cloak x-text="error" class="mt-2 text-sm text-rose-400"></p>
            @error('uploadId') <p class="mt-2 text-sm text-rose-400">{{ $message }}</p> @enderror
            @error('upload') <p class="mt-2 text-sm text-rose-400">{{ $message }}</p> @enderror
            @error('trim') <p class="mt-2 text-sm text-rose-400">{{ $message }}</p> @enderror
            @error('challenge') <p class="mt-2 text-sm text-rose-400">{{ $message }}</p> @enderror
        </section>

        {{-- Fields --}}
        <div class="min-w-0 flex-1 space-y-5">
            <label class="block">
                <span class="mb-2 block text-sm font-semibold">{{ __('challenges.field_title') }}@unless ($isResponse) *@endunless</span>
                <input type="text" maxlength="100" wire:model="title" @disabled($isResponse)
                       placeholder="{{ __('challenges.field_title_placeholder') }}" class="{{ $field }}">
                <span class="mt-1 block text-right text-xs text-white/50" x-text="($wire.title || '').length + '/100'"></span>
                @error('title') <span class="text-sm text-rose-400">{{ $message }}</span> @enderror
            </label>

            <label class="block">
                <span class="mb-2 block text-sm font-semibold">{{ __('challenges.field_rules') }}@unless ($isResponse) *@endunless</span>
                <textarea rows="4" maxlength="500" wire:model="rules" @disabled($isResponse)
                          placeholder="{{ __('challenges.field_rules_placeholder') }}" class="{{ $field }}"></textarea>
                <span class="mt-1 block text-right text-xs text-white/50" x-text="($wire.rules || '').length + '/500'"></span>
                @error('rules') <span class="text-sm text-rose-400">{{ $message }}</span> @enderror
            </label>

            @if ($isResponse)
                {{-- Inherited from the Challenge and locked: a Response can't change the conditions --}}
                <div data-locked="category">
                    <span class="mb-2 block text-sm font-semibold">{{ __('challenges.field_category') }}</span>
                    <div class="flex items-center gap-3 rounded-xl border border-white/10 bg-white/5 px-4 py-3 opacity-70">
                        @if ($challenge->category)
                            <span class="w-6 text-center">{{ $categoryIcons[$challenge->category] ?? '' }}</span>{{ __('challenges.category_'.$challenge->category) }}
                        @else
                            <span class="text-white/50">—</span>
                        @endif
                    </div>
                </div>
                <div data-locked="deadline">
                    <span class="mb-2 block text-sm font-semibold">{{ __('challenges.field_deadline') }}</span>
                    <div class="rounded-xl border border-white/10 bg-white/5 px-4 py-3 opacity-70">
                        <span class="text-white/60">{{ __('challenges.time_left') }}:</span>
                        <span x-data="countdown(@js($challenge->ends_at?->toIso8601String()))" x-init="start()" x-text="label" class="font-semibold"></span>
                    </div>
                </div>
                @if ($challenge->isDuel())
                    <p data-locked="format" class="rounded-xl border border-orange-500/30 bg-orange-500/10 px-4 py-3 text-sm text-orange-200">
                        ⚔️ {{ __('challenges.duel_badge') }} · {{ __('challenges.duel_vs', ['opponent' => '@'.($challenge->opponent->username ?? $challenge->opponent->name ?? '')]) }}
                    </p>
                @endif
                <p class="text-xs text-white/45">{{ __('challenges.response_locked_hint') }}</p>
            @else
                {{-- Category: exactly one, chosen by hand (never inferred from hashtags) --}}
                <div data-field="category" x-data="{ open: false }" @click.outside="open = false" @keydown.escape="open = false" class="relative">
                    <span class="mb-2 block text-sm font-semibold">{{ __('challenges.field_category') }} *</span>
                    <button type="button" @click="open = !open" :aria-expanded="open"
                            class="flex w-full items-center justify-between gap-3 rounded-xl border bg-white/5 px-4 py-3 text-left"
                            :class="open ? 'border-indigo-500' : 'border-white/10'">
                        @if ($category !== '')
                            <span class="flex items-center gap-3"><span class="w-6 text-center">{{ $categoryIcons[$category] ?? '' }}</span>{{ __('challenges.category_'.$category) }}</span>
                        @else
                            <span class="text-white/50">{{ __('challenges.select_category') }}</span>
                        @endif
                        <span class="text-white/60 transition" :class="open && 'rotate-180'">⌄</span>
                    </button>
                    <ul x-show="open" x-cloak x-transition.opacity role="listbox"
                        class="absolute inset-x-0 top-full z-20 mt-2 overflow-hidden rounded-xl border border-white/10 bg-navy-800 py-1 shadow-2xl">
                        @foreach ($categories as $option)
                            <li>
                                <button type="button" role="option" @if ($category === $option) aria-selected="true" @endif
                                        wire:click="$set('category', '{{ $option }}')" @click="open = false; dirty = true"
                                        class="flex w-full items-center gap-3 px-4 py-2.5 text-left text-sm hover:bg-white/10 {{ $category === $option ? 'bg-indigo-600/30' : '' }}">
                                    <span class="w-6 text-center">{{ $categoryIcons[$option] ?? '' }}</span>
                                    {{ __('challenges.category_'.$option) }}
                                </button>
                            </li>
                        @endforeach
                    </ul>
                    @error('category') <span class="text-sm text-rose-400">{{ $message }}</span> @enderror
                </div>

                {{-- Deadline --}}
                <div data-field="deadline">
                    <span class="mb-2 block text-sm font-semibold">{{ __('challenges.field_deadline') }}</span>
                    <div class="grid grid-cols-4 gap-2">
                        @foreach ($durations as $option)
                            <button type="button" wire:click="$set('duration', '{{ $option }}')" @click="dirty = true"
                                    @if ($duration === $option) aria-pressed="true" @endif
                                    class="h-11 rounded-xl border text-sm font-semibold transition {{ $duration === $option ? 'border-indigo-500 bg-indigo-600 text-white' : 'border-white/10 bg-white/5 text-white/70 hover:text-white' }}">
                                {{ __('challenges.duration_'.$option) }}
                            </button>
                        @endforeach
                    </div>
                    <p class="mt-1 text-xs text-white/50">{{ __('challenges.deadline_hint') }}</p>
                    @error('duration') <span class="text-sm text-rose-400">{{ $message }}</span> @enderror
                </div>

                {{-- Format --}}
                <div data-field="format">
                    <span class="mb-2 block text-sm font-semibold">{{ __('challenges.field_format') }}</span>
                    <div class="grid grid-cols-2 gap-2">
                        @foreach ([\App\Models\Challenge::FORMAT_PUBLIC => 'users', \App\Models\Challenge::FORMAT_DUEL => 'swords'] as $option => $icon)
                            <label @class([
                                'flex h-12 cursor-pointer items-center justify-center gap-2 rounded-xl border text-sm font-semibold transition',
                                'border-indigo-500 bg-indigo-600/25 shadow-[0_0_14px_rgba(99,102,241,0.35)]' => $format === $option,
                                'border-white/10 bg-white/5 text-white/80 hover:bg-white/10' => $format !== $option,
                            ])>
                                <input type="radio" name="format" value="{{ $option }}" wire:model.live="format" class="sr-only">
                                @if ($icon === 'users')
                                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><circle cx="9" cy="8" r="3.2" /><path stroke-linecap="round" d="M3.5 19a5.5 5.5 0 0 1 11 0M16 5.2a3 3 0 0 1 0 5.6M17.5 14.2A5.5 5.5 0 0 1 20.5 19" /></svg>
                                @else
                                    <x-icon.swords class="h-5 w-5" />
                                @endif
                                {{ __('challenges.format_'.$option) }}
                            </label>
                        @endforeach
                    </div>
                    <p class="mt-1.5 text-xs text-white/50">{{ __('challenges.format_'.$format.'_hint') }}</p>
                    @error('format') <span class="text-sm text-rose-400">{{ $message }}</span> @enderror

                    @if ($format === \App\Models\Challenge::FORMAT_DUEL)
                        <div data-field="opponent" class="mt-3 rounded-xl border border-white/10 bg-white/[0.03] p-4">
                            <span class="mb-2 block text-sm font-semibold">{{ __('challenges.field_opponent') }} *</span>
                            @if ($opponent)
                                <div class="flex items-center justify-between gap-3 rounded-xl bg-white/5 px-3 py-2">
                                    <span class="flex min-w-0 items-center gap-3">
                                        @if ($opponent->avatarUrl())
                                            <img src="{{ $opponent->avatarUrl() }}" alt="" class="h-8 w-8 rounded-full object-cover">
                                        @else
                                            <span class="flex h-8 w-8 items-center justify-center rounded-full bg-indigo-600 text-sm font-bold">{{ mb_strtoupper(mb_substr($opponent->name, 0, 1)) }}</span>
                                        @endif
                                        <span class="min-w-0">
                                            <span class="block truncate text-sm font-semibold">{{ $opponent->name }}</span>
                                            @if ($opponent->username)
                                                <span class="block truncate text-xs text-white/60">{{ '@'.$opponent->username }}</span>
                                            @endif
                                        </span>
                                    </span>
                                    <button type="button" wire:click="clearOpponent" class="text-sm text-white/60 hover:text-white">{{ __('challenges.opponent_change') }}</button>
                                </div>
                            @else
                                <input type="search" wire:model.live.debounce.300ms="opponentQuery" autocomplete="off"
                                       placeholder="{{ __('challenges.opponent_placeholder') }}" class="{{ $field }}">
                                @if ($opponentResults !== [])
                                    <ul class="mt-2 divide-y divide-white/5 overflow-hidden rounded-xl border border-white/10">
                                        @foreach ($opponentResults as $candidate)
                                            <li wire:key="opponent-{{ $candidate->id }}">
                                                <button type="button" wire:click="selectOpponent({{ $candidate->id }})" @click="dirty = true"
                                                        class="flex w-full items-center gap-3 px-3 py-2 text-left hover:bg-white/10">
                                                    <span class="text-sm font-semibold">{{ $candidate->name }}</span>
                                                    @if ($candidate->username)
                                                        <span class="text-xs text-white/60">{{ '@'.$candidate->username }}</span>
                                                    @endif
                                                </button>
                                            </li>
                                        @endforeach
                                    </ul>
                                @elseif (mb_strlen(trim($opponentQuery, " @")) >= 2)
                                    <p class="mt-2 text-sm text-white/50">{{ __('challenges.opponent_none') }}</p>
                                @endif
                            @endif
                            <div data-invite-link class="mt-3">
                                <span class="mb-1.5 block text-xs font-semibold text-white/70">{{ __('challenges.invite_link') }}</span>
                                <div class="flex items-center justify-between gap-2 rounded-xl border border-dashed border-white/15 px-3 py-2.5 text-xs text-white/45">
                                    <span>{{ __('challenges.invite_link_after') }}</span>
                                    <span aria-hidden="true">🔗</span>
                                </div>
                            </div>
                            <p class="mt-2 text-xs text-white/50">{{ __('challenges.opponent_hint') }}</p>
                            @error('opponent') <span class="text-sm text-rose-400">{{ $message }}</span> @enderror
                        </div>
                    @endif
                </div>
            @endif

            @if ($needsUsername)
                <label class="block">
                    <span class="mb-2 block text-sm font-semibold">{{ __('challenges.field_username') }}</span>
                    <input type="text" maxlength="32" wire:model="username" class="{{ $field }}" placeholder="@username">
                    @error('username') <span class="text-sm text-rose-400">{{ $message }}</span> @enderror
                </label>
            @endif
            @error('daily_limit') <p class="text-sm text-rose-400">{{ $message }}</p> @enderror

            <button type="button"
                    class="flex h-14 w-full items-center justify-center gap-2 rounded-2xl bg-indigo-600 font-semibold uppercase tracking-wide shadow-lg shadow-indigo-600/25 hover:bg-indigo-500 disabled:opacity-50"
                    :disabled="state === 'uploading' || recording || submitting"
                    @click="submitting = true; $wire.publish().finally(() => { submitting = false })">
                ⚡ {{ $isResponse ? __('challenges.publish_response') : __('challenges.publish') }}
            </button>
        </div>
    </div>

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
</div>
