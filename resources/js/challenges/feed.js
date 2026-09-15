const HINT_KEY = 'versus.swipeHintSeen';

const prepare = (challenge) => ({ ...challenge, index: challenge.focus_index ?? 0, viewed: false });

const escapeHtml = (text) => text.replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

export default ({ initial, hintSeen, guest, loginUrl, i18n }) => ({
    challenges: initial.map(prepare),
    i18n,
    active: 0,
    muted: true,
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

    init() {
        let seen = hintSeen;
        if (guest) {
            try { seen = localStorage.getItem(HINT_KEY) === '1'; } catch (e) { seen = false; }
        }
        this.hint = !seen && this.challenges.some((c) => c.slides.length > 1);

        this.$nextTick(() => {
            const first = this.challenges[0];
            const carousel = this.$el.querySelector('[data-carousel="0"]');
            if (first && first.index > 0 && carousel) {
                carousel.scrollLeft = carousel.clientWidth * first.index;
            }
            this.syncPlayback();
        });

        window.addEventListener('pagehide', () => this.flushImpressions());
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

    onVerticalScroll() {
        const el = this.$refs.vertical;
        const index = Math.round(el.scrollTop / Math.max(1, el.clientHeight));
        if (index !== this.active) {
            this.active = index;
            this.syncPlayback();
        }
        if (this.challenges.length - index <= 2) {
            this.loadMore();
        }
    },

    onCarouselScroll(ci, el) {
        const index = Math.round(el.scrollLeft / Math.max(1, el.clientWidth));
        const challenge = this.challenges[ci];
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

        this.$el.querySelectorAll('video[data-ci]').forEach((video) => {
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
                video.muted = this.muted;
                video.play().catch(() => {});
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

    toggleMute() {
        this.muted = !this.muted;
        this.$el.querySelectorAll('video[src]').forEach((video) => { video.muted = this.muted; });
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
        const carousel = this.$el.querySelector(`[data-carousel="${ci}"]`);
        carousel?.scrollTo({ left: carousel.clientWidth * si, behavior: 'smooth' });
    },

    async openComments(ci) {
        const slide = this.currentSlide(ci);
        if (!slide) {
            return;
        }
        this.sheetEntry = slide;
        this.comments = [];
        this.sheet = 'comments';
        this.comments = await this.$wire.comments(this.sheetEntry.entry_id);
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
});
