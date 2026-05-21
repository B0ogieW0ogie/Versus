import './bootstrap';

document.addEventListener('alpine:init', () => {
    window.Alpine.data('countdown', (iso) => ({
        label: '',
        timer: null,
        tick() {
            if (!iso) {
                this.label = '—';
                return;
            }
            const diff = Math.max(0, new Date(iso).getTime() - Date.now());
            const h = String(Math.floor(diff / 3.6e6)).padStart(2, '0');
            const m = String(Math.floor(diff / 6e4) % 60).padStart(2, '0');
            const s = String(Math.floor(diff / 1e3) % 60).padStart(2, '0');
            this.label = `${h}:${m}:${s}`;
        },
        start() {
            this.tick();
            this.timer = setInterval(() => this.tick(), 1000);
        },
        stop() {
            if (this.timer) {
                clearInterval(this.timer);
                this.timer = null;
            }
        },
    }));

    window.Alpine.data('voteWidget', ({
        side,
        poolA,
        poolB,
        max,
        maxCap,
        winnersCut,
        sideALabel,
        sideBLabel,
        i18n,
    }) => ({
        side: side === 'B' ? 'B' : 'A',
        amount: 0,
        poolA: Math.max(0, Number(poolA) || 0),
        poolB: Math.max(0, Number(poolB) || 0),
        max: Math.max(0, Number(max) || 0),
        maxCap: Math.max(0, Number(maxCap) || 0),
        winnersCut: Number(winnersCut) || 0,
        err: null,
        errTimer: null,

        get totalPool() {
            return this.poolA + this.poolB;
        },
        get percentA() {
            return this.totalPool === 0 ? 50 : Math.round((this.poolA * 100) / this.totalPool);
        },
        get percentB() {
            return 100 - this.percentA;
        },
        get chosenPool() {
            return this.side === 'A' ? this.poolA : this.poolB;
        },
        get multiplier() {
            return this.chosenPool === 0 ? null : (this.winnersCut * this.totalPool) / this.chosenPool;
        },
        get multiplierLabel() {
            return this.multiplier === null ? i18n.noMultiplier : this.multiplier.toFixed(2);
        },
        get payout() {
            return this.multiplier === null ? null : Math.round(this.amount * this.multiplier);
        },
        get sideLabel() {
            return this.side === 'A' ? sideALabel : sideBLabel;
        },
        get ctaLabel() {
            return `${i18n.ctaPrefix} ${this.sideLabel} · ${this.amount} 🪙`;
        },
        get payoutLabel() {
            if (this.amount < 1 || this.payout === null) return null;
            return i18n.payoutPreview
                .replace(':side', this.sideLabel)
                .replace(':payout', String(this.payout))
                .replace(':multiplier', this.multiplier.toFixed(2));
        },
        get canSubmit() {
            return this.amount >= 1 && this.amount <= this.max;
        },

        pickSide(s) {
            this.side = s === 'B' ? 'B' : 'A';
        },
        addChip(delta) {
            const base = Number.isFinite(parseInt(this.amount, 10)) ? parseInt(this.amount, 10) : 0;
            const next = Math.max(1, Math.min(this.max, base + Number(delta) || 0));
            this.amount = next;
        },
        setMax() {
            this.amount = this.max;
        },
        clamp(onBlur = false) {
            let n = parseInt(this.amount, 10);
            let changed = false;
            if (isNaN(n)) {
                if (onBlur) { n = 0; changed = true; } else { return; }
            } else if (n < 0) {
                n = 0; changed = true;
            } else if (n > this.max) {
                n = this.max; changed = true;
            }
            if (n !== this.amount) this.amount = n;
            if (changed && n === this.max && this.max > 0) this.flash(i18n.clamp);
        },
        flash(msg) {
            this.err = msg;
            clearTimeout(this.errTimer);
            this.errTimer = setTimeout(() => { this.err = null; }, 2000);
        },
        onBalance(newBalance) {
            const b = Math.max(0, Number(newBalance) || 0);
            this.max = Math.min(b, this.maxCap);
            if (this.amount > this.max) this.amount = this.max;
        },
        onPools(newPoolA, newPoolB) {
            this.poolA = Math.max(0, Number(newPoolA) || 0);
            this.poolB = Math.max(0, Number(newPoolB) || 0);
        },
        submit() {
            this.clamp(true);
            if (!this.canSubmit) return;
            this.$wire.castVote(this.side, this.amount);
            this.amount = 0;
        },
    }));

    window.Alpine.data('versusToast', () => ({
        visible: false,
        title: '',
        body: '',
        _timer: null,
        show(detail) {
            this.title = detail?.title ?? '';
            this.body = detail?.body ?? '';
            this.visible = true;
            clearTimeout(this._timer);
            this._timer = setTimeout(() => {
                this.visible = false;
            }, 4500);
        },
    }));

    window.Alpine.data('voteBattleDual', ({
        totalPool,
        max,
        maxCap,
        walletBalance,
        i18n,
        poolPollUrl = '',
        battleId = 0,
    }) => ({
        i18n,
        battleId: Math.max(0, Number(battleId) || 0),
        totalPool: Math.max(0, Number(totalPool) || 0),
        max: Math.max(0, Number(max) || 0),
        maxCap: Math.max(0, Number(maxCap) || 0),
        walletBalance: Math.max(0, Number(walletBalance) || 0),
        poolPollUrl: typeof poolPollUrl === 'string' ? poolPollUrl : '',
        amountA: 0,
        amountB: 0,
        modalAmount: 0,
        stakeModalSide: null,
        err: null,
        errTimer: null,
        _poolPollTimer: null,

        init() {
            this.$watch('totalPool', (value, oldValue) => {
                if (oldValue === undefined) {
                    return;
                }
                if (Math.round(value) === Math.round(oldValue)) {
                    return;
                }
                this.$nextTick(() => this.bumpPoolAmount());
            });

            if (this.poolPollUrl) {
                this._poolPollTimer = setInterval(() => {
                    this.pollTotalPool();
                }, 5000);
            }
        },

        destroy() {
            if (this._poolPollTimer) {
                clearInterval(this._poolPollTimer);
                this._poolPollTimer = null;
            }
        },

        pollTotalPool() {
            if (!this.poolPollUrl) {
                return;
            }
            fetch(this.poolPollUrl, {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            })
                .then((r) => {
                    if (!r.ok) {
                        throw new Error('pool poll failed');
                    }

                    return r.json();
                })
                .then((data) => {
                    const next = Math.max(0, Number(data.total) || 0);
                    const cur = Math.round(this.totalPool);
                    const nxt = Math.round(next);
                    if (nxt !== cur) {
                        this.totalPool = next;
                    }
                })
                .catch(() => {});
        },

        bumpPoolAmount() {
            const el = this.$refs.poolAmount;
            if (!el) {
                return;
            }
            el.classList.remove('versus-pool-bump');
            void el.offsetWidth;
            el.addEventListener('animationend', () => {
                el.classList.remove('versus-pool-bump');
            }, { once: true });
            el.classList.add('versus-pool-bump');
        },

        parseAmount(key) {
            const n = parseInt(this[key], 10);

            return Number.isFinite(n) ? n : 0;
        },
        canSubmitKey(key) {
            const amt = this.parseAmount(key);

            return amt >= 1 && amt <= this.max;
        },
        canSubmit(side) {
            const key = side === 'A' ? 'amountA' : 'amountB';

            return this.canSubmitKey(key);
        },
        addChipKey(key, delta) {
            const base = this.parseAmount(key);
            const next = Math.max(1, Math.min(this.max, base + (Number(delta) || 0)));
            this[key] = next;
        },
        addChip(side, delta) {
            this.addChipKey(side === 'A' ? 'amountA' : 'amountB', delta);
        },
        setMaxKey(key) {
            this[key] = this.max;
        },
        setMax(side) {
            this.setMaxKey(side === 'A' ? 'amountA' : 'amountB');
        },
        clampKey(key, onBlur = false) {
            let n = parseInt(this[key], 10);
            let changed = false;
            if (Number.isNaN(n)) {
                if (onBlur) {
                    n = 0;
                    changed = true;
                } else {
                    return;
                }
            } else if (n < 0) {
                n = 0;
                changed = true;
            } else if (n > this.max) {
                n = this.max;
                changed = true;
            }
            if (n !== this[key]) {
                this[key] = n;
            }
            if (changed && n === this.max && this.max > 0) {
                this.flash(this.i18n.clamp);
            }
        },
        clamp(side, onBlur = false) {
            this.clampKey(side === 'A' ? 'amountA' : 'amountB', onBlur);
        },
        flash(msg) {
            this.err = msg;
            clearTimeout(this.errTimer);
            this.errTimer = setTimeout(() => {
                this.err = null;
            }, 2000);
        },
        onBalance(newBalance) {
            const b = Math.max(0, Number(newBalance) || 0);
            this.walletBalance = b;
            this.max = Math.min(b, this.maxCap);
            if (this.amountA > this.max) {
                this.amountA = this.max;
            }
            if (this.amountB > this.max) {
                this.amountB = this.max;
            }
            if (this.modalAmount > this.max) {
                this.modalAmount = this.max;
            }
        },
        onStakeSuccess(detail) {
            if (Number(detail?.battleId) !== this.battleId) {
                return;
            }
            if (detail.poolTotal != null) {
                this.totalPool = Math.max(0, Number(detail.poolTotal) || 0);
            }
            this.pollTotalPool();
        },

        openStakeModal(side) {
            if (side !== 'A' && side !== 'B') {
                return;
            }
            this.stakeModalSide = side;
            const key = side === 'A' ? 'amountA' : 'amountB';
            const saved = this.parseAmount(key);
            this.modalAmount = this.max >= 1 && saved < 1 ? 1 : saved;
            this.clampKey('modalAmount', true);
            this.err = null;
            document.body.classList.add('overflow-y-hidden');
        },

        closeStakeModal() {
            this.stakeModalSide = null;
            document.body.classList.remove('overflow-y-hidden');
        },

        confirmStakeModal() {
            if (this.stakeModalSide === null) {
                return;
            }
            this.clampKey('modalAmount', true);
            if (!this.canSubmitKey('modalAmount')) {
                return;
            }
            const key = this.stakeModalSide === 'A' ? 'amountA' : 'amountB';
            this[key] = this.modalAmount;
            this.submit(this.stakeModalSide);
            this.closeStakeModal();
        },

        submit(side) {
            this.clamp(side, true);
            if (!this.canSubmit(side)) {
                return;
            }
            const amt = side === 'A' ? this.amountA : this.amountB;
            this.$wire.castVote(side, amt);
            if (side === 'A') {
                this.amountA = 0;
            } else {
                this.amountB = 0;
            }
        },
    }));

    window.Alpine.data('carousel', ({
        perPageMobile = 2,
        perPageDesktop = 4,
        autoAdvance = false,
        intervalMs = 6000,
        total = 0,
    } = {}) => ({
        perPage: perPageMobile,
        page: 0,
        total: Number(total) || 0,
        autoAdvance: !!autoAdvance,
        intervalMs: Number(intervalMs) || 6000,
        timer: null,
        touchStartX: null,
        mql: null,
        _resizeHandler: null,

        init() {
            this.mql = window.matchMedia('(min-width: 1024px)');
            this.perPage = this.mql.matches ? perPageDesktop : perPageMobile;
            this.mql.addEventListener('change', (e) => {
                this.perPage = e.matches ? perPageDesktop : perPageMobile;
                this.page = Math.min(this.page, this.pageCount - 1);
                requestAnimationFrame(() => this.measureArrowY());
            });
            if (this.autoAdvance && this.pageCount > 1) {
                this.startTimer();
            }
            requestAnimationFrame(() => this.measureArrowY());
            this._resizeHandler = () => requestAnimationFrame(() => this.measureArrowY());
            window.addEventListener('resize', this._resizeHandler);
        },
        destroy() {
            this.stopTimer();
            if (this._resizeHandler) {
                window.removeEventListener('resize', this._resizeHandler);
                this._resizeHandler = null;
            }
        },
        measureArrowY() {
            if (!this.$refs.track) return;
            const firstSlide = this.$refs.track.children[0];
            if (!firstSlide) return;
            const anchor = firstSlide.querySelector('[data-carousel-arrow-anchor]') || firstSlide;
            const rootRect = this.$root.getBoundingClientRect();
            const anchorRect = anchor.getBoundingClientRect();
            const centerY = anchorRect.top + anchorRect.height / 2 - rootRect.top;
            if (Number.isFinite(centerY) && centerY > 0) {
                this.$root.style.setProperty('--carousel-arrow-y', centerY + 'px');
            }
        },
        get pageCount() {
            return Math.max(1, Math.ceil(this.total / this.perPage));
        },
        get isFirst() {
            return this.page <= 0;
        },
        get isLast() {
            return this.page >= this.pageCount - 1;
        },
        get trackStyle() {
            return `transform: translateX(-${this.page * 100}%);`;
        },
        get slideStyle() {
            return `flex: 0 0 ${100 / this.perPage}%; max-width: ${100 / this.perPage}%;`;
        },
        next() {
            if (this.pageCount <= 1) return;
            this.page = (this.page + 1) % this.pageCount;
        },
        prev() {
            if (this.pageCount <= 1) return;
            this.page = (this.page - 1 + this.pageCount) % this.pageCount;
        },
        goTo(page) {
            this.page = Math.max(0, Math.min(page, this.pageCount - 1));
        },
        startTimer() {
            this.stopTimer();
            this.timer = setInterval(() => this.next(), this.intervalMs);
        },
        stopTimer() {
            if (this.timer) {
                clearInterval(this.timer);
                this.timer = null;
            }
        },
        onPause() {
            if (this.autoAdvance) this.stopTimer();
        },
        onResume() {
            if (this.autoAdvance && this.pageCount > 1) this.startTimer();
        },
        onTouchStart(e) {
            this.touchStartX = e.touches?.[0]?.clientX ?? null;
            this.onPause();
        },
        onTouchEnd(e) {
            if (this.touchStartX === null) return;
            const endX = e.changedTouches?.[0]?.clientX ?? this.touchStartX;
            const dx = endX - this.touchStartX;
            if (Math.abs(dx) > 50) {
                if (dx < 0) this.next(); else this.prev();
            }
            this.touchStartX = null;
            this.onResume();
        },
    }));
});

const ONBOARDING_KEYS = ['avatar', 'bio', 'referral', 'balance', 'homeBattles', 'fabCreate', 'feed'];

function syncProfileOnboardingCurtains() {
    const root = document.getElementById('onboarding-curtains');
    const marker = document.getElementById('onboarding-step-marker');
    if (!root) {
        return;
    }
    if (!marker) {
        root.replaceChildren();
        root.style.display = 'none';

        return;
    }
    const raw = marker.dataset.step;
    if (raw === undefined || raw === '') {
        root.replaceChildren();
        root.style.display = 'none';

        return;
    }
    const step = Number.parseInt(raw, 10);
    const key = ONBOARDING_KEYS[step];
    if (!key) {
        root.replaceChildren();
        root.style.display = 'none';

        return;
    }
    const target = document.querySelector(`[data-onboarding-target="${key}"]`);
    root.replaceChildren();
    if (!target) {
        root.style.display = 'none';

        return;
    }
    const pad = 10;
    const r = target.getBoundingClientRect();
    const top = r.top - pad;
    const left = r.left - pad;
    const w = r.width + pad * 2;
    const h = r.height + pad * 2;
    const appendBar = (styles) => {
        const el = document.createElement('div');
        el.className = 'fixed z-[65] bg-navy-950/75 pointer-events-auto';
        Object.assign(el.style, styles);
        root.appendChild(el);
    };
    appendBar({ top: '0', left: '0', right: '0', height: `${Math.max(0, top)}px` });
    appendBar({ top: `${top + h}px`, left: '0', right: '0', bottom: '0' });
    appendBar({ top: `${top}px`, left: '0', width: `${Math.max(0, left)}px`, height: `${h}px` });
    appendBar({ top: `${top}px`, left: `${left + w}px`, right: '0', height: `${h}px` });
    root.style.display = 'block';
}

let onboardingCurtainTimer = null;
function scheduleProfileOnboardingCurtains() {
    window.clearTimeout(onboardingCurtainTimer);
    onboardingCurtainTimer = window.setTimeout(() => {
        window.requestAnimationFrame(syncProfileOnboardingCurtains);
    }, 40);
}

document.addEventListener('livewire:init', () => {
    Livewire.hook('morph.updated', () => scheduleProfileOnboardingCurtains());
});
document.addEventListener('livewire:navigated', () => scheduleProfileOnboardingCurtains());
window.addEventListener('resize', () => scheduleProfileOnboardingCurtains());
window.addEventListener('scroll', () => scheduleProfileOnboardingCurtains(), true);
