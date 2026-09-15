<div x-data="challengeFeed({
        initial: @js($initialSlides),
        hintSeen: @js($hintSeen),
        guest: @js(auth()->guest()),
        loginUrl: @js(route('login')),
        i18n: @js([
            'pillChallenge' => __('challenges.pill_challenge'),
            'pillResponse' => __('challenges.pill_response'),
            'accept' => __('challenges.accept'),
            'vote' => __('challenges.vote'),
            'yourVote' => __('challenges.your_vote'),
            'yourChallenge' => __('challenges.your_challenge'),
            'myResponse' => __('challenges.my_response'),
            'moveVote' => __('challenges.move_vote_confirm'),
            'winner' => __('challenges.winner'),
            'noVotes' => __('challenges.no_votes'),
            'allResponses' => __('challenges.all_responses'),
            'inReplyTo' => __('challenges.in_reply_to'),
            'linkCopied' => __('challenges.link_copied'),
            'soundOn' => __('challenges.sound_on'),
            'soundOff' => __('challenges.sound_off'),
        ]),
     })"
     @pointerdown.capture="unlockSound()" @touchstart.capture="unlockSound()"
     class="fixed inset-x-0 top-0 sm:top-16 bottom-[calc(4.5rem+env(safe-area-inset-bottom))] sm:bottom-0 z-30 bg-black text-white">

    <div x-ref="vertical" class="h-full overflow-y-auto snap-y snap-mandatory overscroll-contain"
         @scroll.debounce.120ms="onVerticalScroll()">

        <template x-if="challenges.length === 0">
            <div class="flex h-full flex-col items-center justify-center gap-4 px-8 text-center text-white/70">
                <p>{{ __('challenges.feed_empty') }}</p>
                @auth
                    <a href="{{ route('challenges.create') }}" class="rounded-full bg-indigo-600 px-5 py-2 text-sm font-semibold text-white">{{ __('challenges.publish') }}</a>
                @endauth
            </div>
        </template>

        <template x-for="(challenge, ci) in challenges" :key="challenge.id">
            <section class="relative h-full w-full snap-start snap-always overflow-hidden">
                {{-- Horizontal carousel: original, responses, "all responses" card --}}
                <div class="flex h-full overflow-x-auto snap-x snap-mandatory [scrollbar-width:none]"
                     :data-carousel="ci"
                     @scroll.debounce.120ms="onCarouselScroll(ci, $event.target)">
                    <template x-for="(slide, si) in challenge.slides" :key="slide.entry_id">
                        <div class="relative h-full w-full shrink-0 snap-start snap-always" @click="togglePlayback(ci)">
                            <video class="h-full w-full bg-black object-contain" playsinline loop muted preload="none"
                                   :poster="slide.poster_url" :data-src="slide.video_url" :data-ci="ci" :data-si="si"></video>
                            <span x-show="slide.is_winner" class="absolute left-4 top-16 rounded-full bg-amber-400/90 px-3 py-1 text-xs font-bold text-black">🏆</span>
                            <span x-show="challenge.paused && challenge.index === si" x-cloak
                                  class="pointer-events-none absolute inset-0 flex items-center justify-center text-6xl text-white/90 drop-shadow-lg">▶</span>
                        </div>
                    </template>
                    <div x-show="challenge.entries_count > 0" class="flex h-full w-full shrink-0 snap-start snap-always items-center justify-center">
                        <button type="button" class="rounded-full border border-white/30 px-6 py-3 text-sm"
                                @click="openAll(ci)" x-text="i18n.allResponses.replace(':count', challenge.entries_count)"></button>
                    </div>
                </div>

                {{-- Overlay --}}
                <div class="pointer-events-none absolute inset-0 flex flex-col justify-between bg-gradient-to-b from-black/40 via-transparent to-black/70">
                    <template x-if="currentSlide(ci)">
                        <div class="flex items-center justify-between p-4 pt-[max(1rem,env(safe-area-inset-top))]">
                            <span class="rounded-full border-2 bg-black/30 px-4 py-1.5 text-sm font-semibold"
                                  :class="currentSlide(ci).is_original ? 'border-orange-500' : 'border-violet-500 shadow-[0_0_14px_rgba(139,92,246,.6)]'"
                                  x-text="currentSlide(ci).is_original ? i18n.pillChallenge : i18n.pillResponse.replace(':n', challenge.index).replace(':total', challenge.slides.length - 1)"></span>
                        </div>
                    </template>

                    <template x-if="currentSlide(ci)">
                        <div class="flex items-end gap-3 px-4 pb-4">
                            <div class="min-w-0 flex-1 space-y-2">
                                <a :href="currentSlide(ci).author.profile_url" class="pointer-events-auto flex items-center gap-2">
                                    <img x-show="currentSlide(ci).author.avatar_url" :src="currentSlide(ci).author.avatar_url" class="h-9 w-9 rounded-full object-cover" alt="">
                                    <span x-show="!currentSlide(ci).author.avatar_url" class="flex h-9 w-9 items-center justify-center rounded-full bg-white/20 text-sm font-semibold" x-text="currentSlide(ci).author.name.charAt(0)"></span>
                                    <span class="truncate font-semibold" x-text="currentSlide(ci).author.name"></span>
                                    <span x-show="!currentSlide(ci).is_original" class="truncate text-xs text-white/60" x-text="i18n.inReplyTo.replace(':name', challenge.author_name)"></span>
                                </a>
                                <p x-show="challenge.is_open" class="text-xs text-white/70">⏱ <span x-data="countdown(challenge.ends_at)" x-init="start()" x-text="label"></span></p>
                                <button type="button" class="pointer-events-auto block w-full text-left" @click="sheetChallenge = challenge; sheet = 'rules'">
                                    <span class="block text-lg font-bold leading-snug" x-text="challenge.title"></span>
                                    <span class="line-clamp-2 text-sm text-white/80" x-text="challenge.rules"></span>
                                </button>
                            </div>

                            <div class="pointer-events-auto flex flex-col items-center gap-4 pb-2 text-xs">
                                <button type="button" @click.stop="toggleSound()" class="flex flex-col items-center gap-1"
                                        :aria-label="(!soundOn || soundBlocked) ? i18n.soundOn : i18n.soundOff">
                                    <span class="text-2xl" x-text="(!soundOn || soundBlocked) ? '🔇' : '🔊'"></span>
                                </button>
                                <button type="button" @click.stop="like(ci)" class="flex flex-col items-center gap-1">
                                    <span class="text-3xl" :class="currentSlide(ci).liked ? 'text-rose-500' : 'text-white'">♥</span>
                                    <span x-text="currentSlide(ci).likes_count"></span>
                                </button>
                                <button type="button" @click.stop="openComments(ci)" class="flex flex-col items-center gap-1">
                                    <span class="text-2xl">💬</span>
                                    <span x-text="currentSlide(ci).comments_count"></span>
                                </button>
                                <button type="button" @click.stop="share(ci)" class="flex flex-col items-center gap-1">
                                    <span class="text-2xl">↪</span>
                                </button>
                                <button type="button" @click.stop="copy(currentSlide(ci).share_url)" class="text-2xl leading-none">•••</button>
                            </div>
                        </div>
                    </template>

                    <div class="px-4 pb-4">
                        <template x-if="challenge.is_open && currentSlide(ci)">
                            <div class="pointer-events-auto flex h-14 w-full text-sm font-semibold uppercase tracking-wide">
                                <button type="button"
                                        class="-mr-2 flex-1 rounded-l-full bg-gradient-to-r from-orange-500 to-orange-400 [clip-path:polygon(0_0,100%_0,calc(100%_-_18px)_100%,0_100%)] disabled:opacity-60"
                                        :disabled="challenge.is_own"
                                        @click="accept(ci)"
                                        x-text="challenge.is_own ? i18n.yourChallenge : (challenge.my_entry_id ? i18n.myResponse : i18n.accept)"></button>
                                <button type="button"
                                        class="-ml-2 flex-1 rounded-r-full bg-gradient-to-r from-indigo-600 to-violet-600 [clip-path:polygon(18px_0,100%_0,100%_100%,0_100%)] disabled:opacity-60"
                                        :disabled="currentSlide(ci).is_mine"
                                        @click="vote(ci)"
                                        x-text="challenge.my_vote_entry_id === currentSlide(ci).entry_id ? i18n.yourVote : i18n.vote"></button>
                            </div>
                        </template>
                        <template x-if="!challenge.is_open">
                            <div class="flex h-14 items-center justify-center rounded-full bg-white/10 text-sm font-semibold"
                                 x-text="challenge.winner_name ? i18n.winner.replace(':name', challenge.winner_name) : i18n.noVotes"></div>
                        </template>
                    </div>
                </div>
            </section>
        </template>
    </div>

    {{-- One bell for the whole page: a Livewire component must not live inside <template x-for>.
         Mobile only: on sm+ the top navigation (with its own bell) is visible above the feed. --}}
    @auth
        <div class="absolute right-4 top-[max(1rem,env(safe-area-inset-top))] z-40 sm:hidden">
            <livewire:notification-bell />
        </div>
    @endauth

    {{-- Sound hint pill: shown while sound is off, or on but still blocked by the browser --}}
    <div x-show="!soundOn || soundBlocked" x-cloak x-transition.opacity
         class="pointer-events-none absolute inset-x-0 top-[max(1rem,env(safe-area-inset-top))] z-40 mx-auto w-fit rounded-full bg-black/60 px-4 py-1.5 text-xs font-medium text-white/90 backdrop-blur">
        🔇 {{ __('challenges.tap_to_unmute') }}
    </div>

    {{-- First-visit swipe hint --}}
    <div x-show="hint" x-cloak x-transition.opacity @click="dismissHint()"
         class="absolute inset-x-0 top-1/2 z-40 mx-6 -translate-y-1/2 rounded-2xl bg-indigo-600/70 px-6 py-5 text-center backdrop-blur">
        <p class="text-lg font-semibold">← {{ __('challenges.swipe_hint_title') }} →</p>
        <p class="text-sm text-white/80">{{ __('challenges.swipe_hint_body') }}</p>
    </div>

    {{-- Bottom sheets --}}
    <div x-show="sheet !== null" x-cloak class="absolute inset-0 z-50 flex items-end bg-black/60" @click.self="sheet = null">
        <div class="max-h-[75%] w-full overflow-y-auto rounded-t-3xl bg-navy-900 p-5">
            <template x-if="sheet === 'rules' && sheetChallenge">
                <div class="space-y-3">
                    <h2 class="text-xl font-bold" x-text="sheetChallenge.title"></h2>
                    <p class="text-sm text-white/60" x-text="sheetChallenge.author_name"></p>
                    <p class="whitespace-pre-line text-sm" x-html="highlightTags(sheetChallenge.rules)"></p>
                    <p x-show="sheetChallenge.is_open" class="text-sm text-white/70">⏱ <span x-data="countdown(sheetChallenge.ends_at)" x-init="start()" x-text="label"></span></p>
                </div>
            </template>

            <template x-if="sheet === 'comments'">
                <div class="space-y-4">
                    <h2 class="text-lg font-bold">{{ __('challenges.comments') }}</h2>
                    <form class="flex gap-2" @submit.prevent="postComment()">
                        <input x-model="commentBody" maxlength="{{ config('versus.challenges.comment_max_length') }}"
                               class="flex-1 rounded-full border-white/10 bg-white/5 text-sm text-white"
                               placeholder="{{ __('challenges.comment_placeholder') }}">
                        <button class="rounded-full bg-indigo-600 px-4 text-sm font-semibold">{{ __('challenges.comment_send') }}</button>
                    </form>
                    <p x-show="comments.length === 0" class="text-sm text-white/50">{{ __('challenges.no_comments') }}</p>
                    <template x-for="comment in comments" :key="comment.id">
                        <div class="text-sm">
                            <span class="font-semibold" x-text="comment.name"></span>
                            <span class="text-xs text-white/40" x-text="comment.time"></span>
                            <p class="whitespace-pre-line text-white/85" x-text="comment.body"></p>
                        </div>
                    </template>
                </div>
            </template>

            <template x-if="sheet === 'all'">
                <div class="grid grid-cols-3 gap-2">
                    <template x-for="item in allResponses" :key="item.entry_id">
                        <a :href="item.url" class="relative aspect-[9/16] overflow-hidden rounded-lg bg-white/5">
                            <img :src="item.poster_url" class="h-full w-full object-cover" alt="">
                            <span class="absolute inset-x-0 bottom-0 truncate bg-black/60 px-1 text-[10px]" x-text="item.name + ' · ' + item.votes_count"></span>
                        </a>
                    </template>
                </div>
            </template>
        </div>
    </div>
</div>
