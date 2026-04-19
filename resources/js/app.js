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
});
