const csrf = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

async function post(url, body) {
    const response = await fetch(url, {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': csrf(), Accept: 'application/json' },
        credentials: 'same-origin',
        body,
    });
    const json = await response.json().catch(() => ({}));
    if (!response.ok) {
        const message = json.errors ? Object.values(json.errors)[0][0] : json.message;
        const error = new Error(message || '');
        error.status = response.status;
        throw error;
    }
    return json;
}

function postChunk(url, index, blob) {
    const form = new FormData();
    form.append('index', String(index));
    form.append('chunk', blob, 'chunk');
    return post(url, form);
}

// Retries network errors and 5xx/429; a 4xx is a real rejection and surfaces immediately.
async function withRetry(fn, attempts = 3) {
    for (let attempt = 1; ; attempt++) {
        try {
            return await fn();
        } catch (error) {
            const retriable = !error.status || error.status >= 500 || error.status === 429;
            if (!retriable || attempt >= attempts) {
                throw error;
            }
            await new Promise((resolve) => setTimeout(resolve, 1000 * attempt));
        }
    }
}

function readDuration(file) {
    return new Promise((resolve) => {
        const video = document.createElement('video');
        const url = URL.createObjectURL(file);
        video.preload = 'metadata';
        video.onloadedmetadata = () => {
            URL.revokeObjectURL(url);
            resolve(Number.isFinite(video.duration) ? video.duration : null);
        };
        video.onerror = () => {
            URL.revokeObjectURL(url);
            resolve(null);
        };
        video.src = url;
    });
}

export default ({ maxSeconds, maxBytes, urls, backUrl, i18n }) => ({
    state: 'idle',
    progress: 0,
    previewUrl: null,
    error: '',
    dirty: false,
    submitting: false,
    confirmLeave: false,

    init() {
        window.addEventListener('beforeunload', (event) => {
            if (this.dirty && !this.submitting) {
                event.preventDefault();
                event.returnValue = '';
            }
        });
    },

    pick() {
        if (this.state !== 'uploading') {
            this.$refs.file.click();
        }
    },

    back() {
        if (this.dirty) {
            this.confirmLeave = true;
            return;
        }
        window.location.href = backUrl;
    },

    leave() {
        this.dirty = false;
        window.location.href = backUrl;
    },

    async onFile(event) {
        const file = event.target.files[0];
        event.target.value = '';
        if (!file) {
            return;
        }
        this.error = '';
        if (file.size > maxBytes) {
            this.error = i18n.tooBig;
            return;
        }
        const duration = await readDuration(file);
        if (duration === null) {
            this.error = i18n.failed;
            return;
        }
        if (duration > maxSeconds + 0.5) {
            this.error = i18n.tooLong;
            return;
        }

        if (this.previewUrl) {
            URL.revokeObjectURL(this.previewUrl);
        }
        this.previewUrl = URL.createObjectURL(file);
        this.dirty = true;
        await this.upload(file);
    },

    async upload(file) {
        this.state = 'uploading';
        this.progress = 0;
        this.$wire.set('uploadId', '', false);

        try {
            const { upload_id: id, chunk_bytes: size } = await withRetry(() => post(urls.start));
            const total = Math.max(1, Math.ceil(file.size / size));
            for (let index = 0; index < total; index++) {
                const blob = file.slice(index * size, (index + 1) * size);
                await withRetry(() => postChunk(urls.chunk.replace('__ID__', id), index, blob));
                this.progress = Math.round(((index + 1) / total) * 100);
            }
            await withRetry(() => post(urls.complete.replace('__ID__', id)));
            this.$wire.set('uploadId', id, false);
            this.state = 'done';
        } catch (error) {
            this.state = 'idle';
            this.error = error.message || i18n.failed;
        }
    },
});
