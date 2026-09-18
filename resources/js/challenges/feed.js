const HINT_KEY = 'versus.swipeHintSeen';
const SOUND_KEY = 'versus.sound';
const VOLUME_KEY = 'versus.volume';

const prepare = (challenge) => ({ ...challenge, index: challenge.focus_index ?? 0, viewed: false, correcting: false, correctingTimer: null, paused: false });

const TAG_RE = /#[\p{L}\p{N}_]+/gu;
const PLAYER_EVENTS = ['timeupdate', 'loadedmetadata', 'durationchange', 'play', 'pause', 'emptied'];

const isDesktop = () => window.matchMedia('(min-width: 1024px)').matches;

const escapeHtml = (text) => text.replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

export default ({ initial, hintSeen, guest, loginUrl, i18n }) => {
    // The feed root, captured once in init(). `this.$el` inside a method is the element whose
    // handler invoked it (e.g. the mute button), not the root — querying from it misses videos.
    let root = null;
    // Desktop player bar: the <video> whose events we listen to, and the bound handlers.
    let playerVideo = null;
    let onPlayerEvent = null;
    let onKeydown = null;

    return {
    challenges: initial.map(prepare),
    i18n,
    active: 0,
    correctingVertical: false,
    correctingVerticalTimer: null,
    soundOn: true,
    soundBlocked: false,
    volume: 1,
    volumeOpen: false,
    draggingVolume: false,
    skipNextTap: false,
    skipTapTimer: null,
    loading: false,
    exhausted: false,
    hint: false,
    sheet: null,
    sheetChallenge: null,
    sheetEntry: null,
    comments: [],
    commentBody: '',
    allResponses: [],
    impressionQueue: new Set(),
    impressionTimer: null,
    dwellTimer: null,
    player: { current: 0, duration: 0, paused: true },
    detailsExpanded: false,

    init() {
        root = this.$el;
        onPlayerEvent = () => this.updatePlayer();
        onKeydown = (event) => this.onKeydown(event);

        let seen = hintSeen;
        if (guest) {
            try { seen = localStorage.getItem(HINT_KEY) === '1'; } catch (e) { seen = false; }
        }
        this.hint = !seen && this.challenges.some((c) => c.slides.length > 1);

        try {
            this.soundOn = localStorage.getItem(SOUND_KEY) !== 'off';
            const savedVolume = parseFloat(localStorage.getItem(VOLUME_KEY));
            if (Number.isFinite(savedVolume)) {
                this.volume = Math.min(1, Math.max(0, savedVolume));
            }
        } catch (e) {
            this.soundOn = true;
        }

        this.$nextTick(() => {
            const first = this.challenges[0];
            const carousel = root.querySelector('[data-carousel="0"]');
            if (first && first.index > 0 && carousel) {
                carousel.scrollLeft = carousel.clientWidth * first.index;
            }
            this.syncPlayback();
            this.loadDesktopComments();
        });

        // Desktop details column follows the current slide.
        this.$watch("active + ':' + (challenges[active] ? challenges[active].index : '')", () => {
            this.detailsExpanded = false;
            this.loadDesktopComments();
        });

        window.addEventListener('pagehide', () => this.flushImpressions());
        window.addEventListener('keydown', onKeydown);
    },

    destroy() {
        window.removeEventListener('keydown', onKeydown);
        this.bindPlayer(null);
    },

    // Returns null when the "all responses" end card is the active horizontal slide
    // (index === slides.length) — that card carries no entry to act on.
    currentSlide(ci) {
        const challenge = this.challenges[ci];
        if (!challenge || challenge.index >= challenge.slides.length) {
            return null;
        }

        return challenge.slides[challenge.index];
    },

    // A long/fast fling can land more than one slide away even with scroll-snap on
    // (some browsers ignore scroll-snap-stop). If the settled index is more than one
    // away from `active`, snap back to a single-slide step in the direction of travel.
    onVerticalScroll() {
        const el = this.$refs.vertical;
        const index = Math.round(el.scrollTop / Math.max(1, el.clientHeight));

        if (this.correctingVertical) {
            if (index === this.active) {
                this.correctingVertical = false;
                clearTimeout(this.correctingVerticalTimer);
            }
            return;
        }

        if (Math.abs(index - this.active) > 1) {
            const corrected = index > this.active ? this.active + 1 : this.active - 1;
            this.correctingVertical = true;
            this.active = corrected;
            el.scrollTo({ top: corrected * el.clientHeight, behavior: 'smooth' });
            this.syncPlayback();
            // Safety net: an interrupted/cancelled smooth scroll may never settle at
            // `corrected`, which would otherwise leave paging stuck forever.
            clearTimeout(this.correctingVerticalTimer);
            this.correctingVerticalTimer = setTimeout(() => { this.correctingVertical = false; }, 700);
            return;
        }

        if (index !== this.active) {
            this.active = index;
            this.syncPlayback();
        }
        if (this.challenges.length - index <= 2) {
            this.loadMore();
        }
    },

    // Same one-slide-per-fling guarantee for the horizontal (response) carousel.
    onCarouselScroll(ci, el) {
        const challenge = this.challenges[ci];
        const index = Math.round(el.scrollLeft / Math.max(1, el.clientWidth));

        if (challenge.correcting) {
            if (index === challenge.index) {
                challenge.correcting = false;
                clearTimeout(challenge.correctingTimer);
            }
            return;
        }

        if (Math.abs(index - challenge.index) > 1) {
            const corrected = index > challenge.index ? challenge.index + 1 : challenge.index - 1;
            challenge.correcting = true;
            challenge.index = corrected;
            el.scrollTo({ left: corrected * el.clientWidth, behavior: 'smooth' });
            this.syncPlayback();
            // Safety net: an interrupted/cancelled smooth scroll may never settle at
            // `corrected`, which would otherwise leave this carousel stuck forever.
            clearTimeout(challenge.correctingTimer);
            challenge.correctingTimer = setTimeout(() => { challenge.correcting = false; }, 700);
            if (corrected > 0) {
                this.dismissHint();
            }
            return;
        }

        if (index !== challenge.index) {
            challenge.index = index;
            this.syncPlayback();
        }
        if (index > 0) {
            this.dismissHint();
        }
    },

    // Only the current video plays; at most three <video> elements hold a src (iOS memory).
    syncPlayback() {
        clearTimeout(this.dwellTimer);
        const a = this.active;
        const current = this.challenges[a];
        if (!current) {
            return;
        }
        const next = this.challenges[a + 1];
        const currentKey = `${a}:${current.index}`;
        const keep = new Set([currentKey, `${a}:${current.index + 1}`, next ? `${a + 1}:${next.index}` : '']);
        this.bindPlayer(this.currentVideo());

        root.querySelectorAll('video[data-ci]').forEach((video) => {
            const key = `${video.dataset.ci}:${video.dataset.si}`;
            if (keep.has(key)) {
                if (video.getAttribute('src') !== video.dataset.src) {
                    video.preload = key === currentKey ? 'auto' : 'metadata';
                    video.src = video.dataset.src;
                }
            } else if (video.hasAttribute('src')) {
                video.pause();
                video.removeAttribute('src');
                video.load();
            }

            if (key === currentKey) {
                current.paused = false;
                video.volume = this.volume;
                video.muted = this.isMuted();
                video.play().then(() => {
                    if (!video.muted) {
                        this.soundBlocked = false;
                    }
                }).catch((error) => {
                    if (!video.muted && error?.name === 'NotAllowedError') {
                        // Autoplay-with-sound was blocked — fall back to muted playback
                        // and surface the hint pill until a user gesture unlocks sound.
                        video.muted = true;
                        this.soundBlocked = true;
                        video.play().catch(() => {});
                    }
                });
            } else {
                video.pause();
            }
        });

        this.dwellTimer = setTimeout(() => this.onDwell(), 1000);
    },

    onDwell() {
        const challenge = this.challenges[this.active];
        if (!challenge) {
            return;
        }
        if (!challenge.viewed) {
            challenge.viewed = true;
            this.$wire.markViewed(challenge.id);
        }
        const slide = this.currentSlide(this.active);
        if (slide && !slide.is_original) {
            this.impressionQueue.add(slide.entry_id);
            if (!this.impressionTimer) {
                this.impressionTimer = setTimeout(() => this.flushImpressions(), 5000);
            }
        }
    },

    flushImpressions() {
        clearTimeout(this.impressionTimer);
        this.impressionTimer = null;
        if (this.impressionQueue.size === 0) {
            return;
        }
        const ids = [...this.impressionQueue];
        this.impressionQueue.clear();
        this.$wire.recordImpressions(ids);
    },

    // The <video> element currently playing (active vertical slide, active carousel index).
    currentVideo() {
        const challenge = this.challenges[this.active];
        if (!challenge) {
            return null;
        }
        return root.querySelector(`video[data-ci="${this.active}"][data-si="${challenge.index}"]`);
    },

    // Exposed for the view (hover slider is desktop-only).
    isDesktop() {
        return isDesktop();
    },

    // Muted when the user turned sound off, dragged the volume to zero, or the browser blocked it.
    isMuted() {
        return !this.soundOn || this.volume === 0;
    },

    soundMuted() {
        return this.isMuted() || this.soundBlocked;
    },

    // Volume slider (desktop, on hover). Zero means muted; dragging up unmutes.
    setVolumeFromPointer(event) {
        const track = event.currentTarget.getBoundingClientRect();
        const ratio = 1 - (event.clientY - track.top) / Math.max(1, track.height);
        this.applyVolume(Math.min(1, Math.max(0, Math.round(ratio * 100) / 100)));
    },

    startVolumeDrag(event) {
        this.draggingVolume = true;
        const track = event.currentTarget;
        this.setVolumeFromPointer(event);
        const move = (moveEvent) => {
            const rect = track.getBoundingClientRect();
            const ratio = 1 - (moveEvent.clientY - rect.top) / Math.max(1, rect.height);
            this.applyVolume(Math.min(1, Math.max(0, Math.round(ratio * 100) / 100)));
        };
        const stop = () => {
            this.draggingVolume = false;
            window.removeEventListener('pointermove', move);
            window.removeEventListener('pointerup', stop);
        };
        window.addEventListener('pointermove', move);
        window.addEventListener('pointerup', stop);
    },

    applyVolume(value) {
        this.volume = value;
        try { localStorage.setItem(VOLUME_KEY, String(value)); } catch (e) { /* storage blocked */ }

        if (value > 0 && !this.soundOn) {
            this.soundOn = true;
            try { localStorage.setItem(SOUND_KEY, 'on'); } catch (e) { /* storage blocked */ }
        }

        const video = this.currentVideo();
        if (!video) {
            return;
        }
        video.volume = value;
        if (value > 0 && this.soundOn) {
            this.enableSound(video);
        } else {
            video.muted = true;
        }
    },

    // Sound counts as "on" only when wanted AND not blocked by the browser's autoplay policy,
    // so the 🔇 button (shown while blocked) turns sound on instead of saving "off".
    toggleSound() {
        const effectivelyOn = !this.soundMuted();
        this.soundOn = !effectivelyOn;
        try { localStorage.setItem(SOUND_KEY, this.soundOn ? 'on' : 'off'); } catch (e) { /* storage blocked */ }

        if (this.soundOn && this.volume === 0) {
            // Unmuting a slider dragged to zero: bring it back to a usable level.
            this.volume = 1;
            try { localStorage.setItem(VOLUME_KEY, '1'); } catch (e) { /* storage blocked */ }
        }

        const video = this.currentVideo();
        if (!video) {
            return;
        }
        video.volume = this.volume;
        if (this.soundOn) {
            this.enableSound(video);
        } else {
            video.muted = true;
            this.soundBlocked = false;
        }
    },

    // Must run inside a user activation (a click/tap). Browsers ignore unmuting on
    // touch pointerdown and may pause an unmuted video, so always (re)call play().
    enableSound(video) {
        video.volume = this.volume;
        if (this.isMuted()) {
            video.muted = true;

            return;
        }
        video.muted = false;
        video.play().then(() => {
            this.soundBlocked = false;
        }).catch(() => {
            video.muted = true;
            this.soundBlocked = true;
            video.play().catch(() => {});
        });
    },

    // First tap anywhere on the feed (click capture): if sound is wanted but blocked,
    // unlock it inside this gesture. A tap on the video area must not also toggle pause.
    unlockSound(event) {
        if (!this.soundBlocked || this.isMuted()) {
            return;
        }
        if (event?.target?.closest?.('[data-sound-toggle]')) {
            return; // the sound button handles this tap itself
        }
        const video = this.currentVideo();
        if (!video) {
            return;
        }
        if (event?.target?.closest?.('[data-slide], [data-player-toggle]')) {
            this.skipNextTap = true;
            clearTimeout(this.skipTapTimer);
            this.skipTapTimer = setTimeout(() => { this.skipNextTap = false; }, 400);
        }
        this.enableSound(video);
    },

    togglePlayback(ci) {
        if (this.skipNextTap) {
            this.skipNextTap = false;
            clearTimeout(this.skipTapTimer);
            return;
        }
        const challenge = this.challenges[ci];
        const slide = this.currentSlide(ci);
        if (!challenge || !slide) {
            return;
        }
        const video = root.querySelector(`video[data-ci="${ci}"][data-si="${challenge.index}"]`);
        if (!video) {
            return;
        }
        if (video.paused) {
            video.play().then(() => { challenge.paused = false; }).catch(() => {
                // Playback failed to resume — leave the paused (▶) state as-is.
            });
        } else {
            video.pause();
            challenge.paused = true;
        }
    },

    // Desktop player bar: follow exactly one <video> (the current one).
    bindPlayer(video) {
        if (playerVideo !== video) {
            if (playerVideo) {
                PLAYER_EVENTS.forEach((name) => playerVideo.removeEventListener(name, onPlayerEvent));
            }
            playerVideo = video;
            if (video) {
                PLAYER_EVENTS.forEach((name) => video.addEventListener(name, onPlayerEvent));
            }
        }
        this.updatePlayer();
    },

    updatePlayer() {
        const video = playerVideo;
        this.player.current = video ? video.currentTime || 0 : 0;
        this.player.duration = video && Number.isFinite(video.duration) ? video.duration : 0;
        this.player.paused = video ? video.paused : true;
    },

    seek(event) {
        const video = playerVideo;
        if (!video || !this.player.duration) {
            return;
        }
        const rect = event.currentTarget.getBoundingClientRect();
        const ratio = Math.min(1, Math.max(0, (event.clientX - rect.left) / Math.max(1, rect.width)));
        video.currentTime = ratio * this.player.duration;
        this.updatePlayer();
    },

    // Desktop keyboard: ↑/↓ = previous/next challenge, ←/→ = previous/next slide.
    onKeydown(event) {
        if (!isDesktop() || event.defaultPrevented || event.altKey || event.ctrlKey || event.metaKey) {
            return;
        }
        if (event.target?.closest?.('input, textarea, select, [contenteditable]')) {
            return;
        }
        const challenge = this.challenges[this.active];
        switch (event.key) {
            case 'ArrowDown':
                this.goToChallenge(this.active + 1);
                break;
            case 'ArrowUp':
                this.goToChallenge(this.active - 1);
                break;
            case 'ArrowRight':
                if (challenge) {
                    const last = challenge.entries_count > 0 ? challenge.slides.length : challenge.slides.length - 1;
                    this.goToSlide(this.active, Math.min(last, challenge.index + 1));
                }
                break;
            case 'ArrowLeft':
                if (challenge) {
                    this.goToSlide(this.active, challenge.index - 1);
                }
                break;
            default:
                return;
        }
        event.preventDefault();
    },

    goToChallenge(ci) {
        if (ci < 0 || ci >= this.challenges.length || ci === this.active) {
            return;
        }
        const el = this.$refs.vertical;
        // Same in-flight correction guard as goToSlide, so onVerticalScroll doesn't fight it.
        this.correctingVertical = true;
        this.active = ci;
        el.scrollTo({ top: ci * el.clientHeight, behavior: 'smooth' });
        this.syncPlayback();
        clearTimeout(this.correctingVerticalTimer);
        this.correctingVerticalTimer = setTimeout(() => { this.correctingVertical = false; }, 700);
        if (this.challenges.length - ci <= 2) {
            this.loadMore();
        }
    },

    async loadMore() {
        if (this.loading || this.exhausted) {
            return;
        }
        this.loading = true;
        try {
            const batch = await this.$wire.loadMore();
            if (!batch.length) {
                this.exhausted = true;
                return;
            }
            this.challenges.push(...batch.map(prepare));
            // Attach sources to the freshly rendered slides (the next one may need preloading).
            this.$nextTick(() => this.syncPlayback());
        } catch (error) {
            // A failed request is transient: leave `exhausted` alone so the next scroll retries.
            console.error(error);
        } finally {
            this.loading = false;
        }
    },

    handle(result) {
        if (result?.redirect) {
            window.location.href = result.redirect;
            return false;
        }
        if (result?.error) {
            this.toast(result.error);
            return false;
        }
        return true;
    },

    toast(title) {
        window.dispatchEvent(new CustomEvent('versus-stake-toast', { detail: { title } }));
    },

    requireLogin() {
        if (guest) {
            window.location.href = loginUrl;
            return true;
        }
        return false;
    },

    async like(ci) {
        const slide = this.currentSlide(ci);
        if (!slide) {
            return;
        }
        const result = await this.$wire.toggleLike(slide.entry_id);
        if (this.handle(result)) {
            slide.liked = result.liked;
            slide.likes_count = result.likes_count;
        }
    },

    async vote(ci) {
        if (this.requireLogin()) {
            return;
        }
        const challenge = this.challenges[ci];
        const slide = this.currentSlide(ci);
        if (!slide || slide.is_mine || !challenge.is_open || challenge.my_vote_entry_id === slide.entry_id) {
            return;
        }
        if (challenge.my_vote_entry_id && !window.confirm(this.i18n.moveVote)) {
            return;
        }
        const result = await this.$wire.vote(slide.entry_id);
        if (this.handle(result)) {
            challenge.my_vote_entry_id = result.my_vote_entry_id;
            challenge.slides.forEach((s) => {
                if (result.counts[s.entry_id] !== undefined) {
                    s.votes_count = result.counts[s.entry_id];
                }
            });
        }
    },

    async accept(ci) {
        if (this.requireLogin()) {
            return;
        }
        const challenge = this.challenges[ci];
        if (challenge.my_entry_id) {
            const si = challenge.slides.findIndex((s) => s.entry_id === challenge.my_entry_id);
            if (si === -1) {
                // My response isn't in this carousel batch (e.g. rotated out) — deep-link to it.
                window.location.href = `/c/${challenge.slug}?entry=${challenge.my_entry_id}`;
                return;
            }
            this.goToSlide(ci, si);
            return;
        }
        const result = await this.$wire.accept(challenge.id);
        if (this.handle(result)) {
            window.location.href = result.respond_url;
        }
    },

    goToSlide(ci, si) {
        if (si < 0) {
            return;
        }
        const challenge = this.challenges[ci];
        if (!challenge || si === challenge.index) {
            // Already there — nothing to scroll, so no scroll event will ever fire to
            // clear a `correcting` flag we might otherwise set.
            return;
        }
        const carousel = root.querySelector(`[data-carousel="${ci}"]`);
        if (!carousel) {
            return;
        }
        // This can be more than one slide away from the current index (e.g. deep-linking
        // to "my response") — pre-set the target and mark it as an in-flight correction so
        // the one-slide-per-fling guard in onCarouselScroll doesn't snap it back.
        challenge.correcting = true;
        challenge.index = si;
        carousel.scrollTo({ left: carousel.clientWidth * si, behavior: 'smooth' });
        this.syncPlayback();
        clearTimeout(challenge.correctingTimer);
        challenge.correctingTimer = setTimeout(() => { challenge.correcting = false; }, 700);
    },

    async openComments(ci) {
        const slide = this.currentSlide(ci);
        if (!slide) {
            return;
        }
        if (isDesktop()) {
            // On desktop the comments live in the details column — focus its input instead.
            root.querySelector('[data-desktop-comment-input]')?.focus();
            return;
        }
        this.sheetEntry = slide;
        this.comments = [];
        this.sheet = 'comments';
        this.comments = await this.$wire.comments(this.sheetEntry.entry_id);
    },

    // Desktop details column: comments for the current slide (shares state with the mobile sheet,
    // which is never open at lg).
    async loadDesktopComments() {
        if (!isDesktop()) {
            return;
        }
        const slide = this.currentSlide(this.active);
        this.sheetEntry = slide;
        this.comments = [];
        if (!slide) {
            return;
        }
        const entryId = slide.entry_id;
        try {
            const rows = await this.$wire.comments(entryId);
            // Ignore a stale response if the slide changed while loading.
            if (this.sheetEntry?.entry_id === entryId) {
                this.comments = rows;
            }
        } catch (error) {
            console.error(error);
        }
    },

    async postComment() {
        const body = this.commentBody.trim();
        if (!body || this.requireLogin()) {
            return;
        }
        const result = await this.$wire.postComment(this.sheetEntry.entry_id, body);
        if (this.handle(result)) {
            this.comments.unshift(result.comment);
            this.sheetEntry.comments_count++;
            this.commentBody = '';
        }
    },

    async openAll(ci) {
        this.allResponses = [];
        this.sheet = 'all';
        this.allResponses = await this.$wire.allResponses(this.challenges[ci].id);
    },

    async share(ci) {
        const slide = this.currentSlide(ci);
        if (!slide) {
            return;
        }
        if (navigator.share) {
            try {
                await navigator.share({ title: this.challenges[ci].title, url: slide.share_url });
            } catch (e) {
                // User dismissed the share sheet.
            }
            return;
        }
        await this.copy(slide.share_url);
    },

    async copy(url) {
        try {
            await navigator.clipboard.writeText(url);
            this.toast(this.i18n.linkCopied);
        } catch (e) {
            // Clipboard unavailable (insecure context).
        }
    },

    tagsOf(text) {
        return [...new Set((text || '').match(TAG_RE) || [])];
    },

    stripTags(text) {
        return (text || '').replace(TAG_RE, '').replace(/[ \t]{2,}/g, ' ').replace(/[ \t]+$/gm, '').trim();
    },

    isLongText(text) {
        return text.length > 90 || text.includes('\n');
    },

    highlightTags(text) {
        return escapeHtml(text).replace(/#([\p{L}\p{N}_]+)/gu, '<span class="text-violet-400">#$1</span>');
    },

    dismissHint() {
        if (!this.hint) {
            return;
        }
        this.hint = false;
        if (guest) {
            try { localStorage.setItem(HINT_KEY, '1'); } catch (e) { /* storage blocked */ }
        } else {
            this.$wire.dismissSwipeHint();
        }
    },
};
};
