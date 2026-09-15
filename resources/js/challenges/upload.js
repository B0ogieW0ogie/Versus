const csrf = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

async function request(method, url, body) {
    const response = await fetch(url, {
        method,
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

const post = (url, body) => request('POST', url, body);
const get = (url) => request('GET', url);

function postChunk(url, index, blob) {
    const form = new FormData();
    form.append('index', String(index));
    form.append('chunk', blob, 'chunk');
    return post(url, form);
}

// Delays between retries of one request: ~30 s in total before giving up.
const BACKOFF_MS = [1000, 2000, 4000, 8000, 15000];

// Network errors and 5xx/429 are transient; any other 4xx is a real rejection and surfaces immediately.
const isRetriable = (error) => !error.status || error.status >= 500 || error.status === 429;

const sleep = (ms) => new Promise((resolve) => setTimeout(resolve, ms));

// While the browser reports itself offline there is no point burning retries: wait for connectivity.
function waitOnline() {
    if (navigator.onLine !== false) {
        return Promise.resolve();
    }
    return new Promise((resolve) => window.addEventListener('online', () => resolve(), { once: true }));
}

async function backoff(attempt) {
    await waitOnline();
    await sleep(BACKOFF_MS[attempt]);
    await waitOnline();
}

async function withRetry(fn) {
    for (let attempt = 0; ; attempt++) {
        try {
            return await fn();
        } catch (error) {
            if (!isRetriable(error) || attempt >= BACKOFF_MS.length) {
                throw error;
            }
            await backoff(attempt);
        }
    }
}

// Asks the server which chunk it expects next, so a dropped upload resumes instead of restarting.
async function resumePoint(statusUrl, fallback) {
    try {
        const status = await get(statusUrl);
        return Number.isInteger(status?.next_chunk) ? status.next_chunk : fallback;
    } catch (error) {
        if (!isRetriable(error)) {
            throw error; // e.g. the upload expired or is not ours: restarting is the only option.
        }
        return fallback; // Still unreachable: the chunk retry below backs off again.
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
        // Some containers/codecs expose no readable duration in the browser; the server's ffprobe decides then.
        const duration = await readDuration(file);
        if (Number.isFinite(duration) && duration > maxSeconds + 0.5) {
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
            await this.sendChunks(file, id, size);
            await withRetry(() => post(urls.complete.replace('__ID__', id)));
            this.$wire.set('uploadId', id, false);
            this.state = 'done';
        } catch (error) {
            this.state = 'idle';
            this.error = error.message || i18n.failed;
        }
    },

    async sendChunks(file, id, size) {
        const chunkUrl = urls.chunk.replace('__ID__', id);
        const statusUrl = urls.status.replace('__ID__', id);
        const total = Math.max(1, Math.ceil(file.size / size));
        let index = 0;
        let attempt = 0;

        while (index < total) {
            try {
                const status = await postChunk(chunkUrl, index, file.slice(index * size, (index + 1) * size));
                index = Number.isInteger(status?.next_chunk) ? status.next_chunk : index + 1;
                attempt = 0;
                this.progress = Math.round((Math.min(index, total) / total) * 100);
            } catch (error) {
                if (!isRetriable(error) || attempt >= BACKOFF_MS.length) {
                    throw error;
                }
                await backoff(attempt++);
                const resumed = await resumePoint(statusUrl, index);
                if (resumed !== index) {
                    // The server already has this chunk (only the response was lost): move on with a fresh budget.
                    index = resumed;
                    attempt = 0;
                    this.progress = Math.round((Math.min(index, total) / total) * 100);
                }
            }
        }
    },
});
