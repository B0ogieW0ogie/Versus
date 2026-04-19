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
});
