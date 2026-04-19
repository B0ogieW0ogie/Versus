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

    window.Alpine.data('voteForm', (max, clampMsg) => ({
        amount: 1,
        max: Math.max(0, Number(max) || 0),
        err: null,
        errTimer: null,
        clamp(onBlur = false) {
            let n = parseInt(this.amount, 10);
            let changed = false;
            if (isNaN(n)) {
                n = onBlur ? 1 : this.amount;
                if (onBlur) changed = true;
            } else if (n < 1) {
                n = 1;
                changed = true;
            } else if (n > this.max) {
                n = this.max;
                changed = true;
            }
            if (n !== this.amount) this.amount = n;
            if (changed) this.flash(clampMsg);
        },
        flash(msg) {
            this.err = msg;
            clearTimeout(this.errTimer);
            this.errTimer = setTimeout(() => { this.err = null; }, 2000);
        },
        submit(side) {
            this.clamp(true);
            if (this.amount < 1 || this.amount > this.max) return;
            this.$wire.voteFor(side, this.amount);
        },
    }));
});
