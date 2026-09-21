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

// Safari records MP4, Chromium/Firefox WebM; ffmpeg on the server transcodes either.
const RECORDER_TYPES = ['video/mp4', 'video/webm;codecs=vp9,opus', 'video/webm;codecs=vp8,opus', 'video/webm'];

const recorderType = () => RECORDER_TYPES.find((type) => window.MediaRecorder?.isTypeSupported?.(type)) ?? '';

// Phones get the native camera through <input capture>; in-page recording is for desktops with a webcam.
const prefersNativeCamera = () => window.matchMedia('(pointer: coarse)').matches || !navigator.mediaDevices?.getUserMedia || !window.MediaRecorder;

export default ({ maxSeconds, maxBytes, urls, backUrl, i18n }) => ({
    state: 'idle',
    progress: 0,
    previewUrl: null,
    error: '',
    dirty: false,
    submitting: false,
    confirmLeave: false,
    // Trimmer (seconds). duration is null when the browser can't read it; the server decides then.
    duration: null,
    trimStart: 0,
    trimEnd: 0,
    camera: false,
    recording: false,
    recordedSeconds: 0,
    stream: null,
    recorder: null,
    timer: null,

    init() {
        window.addEventListener('pagehide', () => this.closeCamera());
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

    async openCamera() {
        if (this.state === 'uploading') {
            return;
        }
        if (prefersNativeCamera()) {
            this.$refs.capture.click();
            return;
        }
        this.error = '';
        try {
            this.stream = await navigator.mediaDevices.getUserMedia({
                video: { facingMode: 'user', width: { ideal: 720 }, height: { ideal: 1280 }, aspectRatio: { ideal: 9 / 16 } },
                audio: true,
            });
        } catch {
            this.error = i18n.cameraDenied;
            return;
        }
        this.camera = true;
        this.$nextTick(() => {
            this.$refs.live.srcObject = this.stream;
        });
    },

    startRecording() {
        const type = recorderType();
        const chunks = [];
        this.recorder = new MediaRecorder(this.stream, type ? { mimeType: type } : undefined);
        this.recorder.ondataavailable = (event) => event.data.size && chunks.push(event.data);
        this.recorder.onstop = () => {
            const mime = this.recorder.mimeType || type || 'video/webm';
            const file = new File(chunks, `recording.${mime.includes('mp4') ? 'mp4' : 'webm'}`, { type: mime });
            this.closeCamera();
            this.useFile(file);
        };
        this.recorder.start(1000);
        this.recording = true;
        this.recordedSeconds = 0;
        this.dirty = true;
        this.timer = setInterval(() => {
            this.recordedSeconds++;
            if (this.recordedSeconds >= maxSeconds) {
                this.stopRecording();
            }
        }, 1000);
    },

    stopRecording() {
        clearInterval(this.timer);
        this.recording = false;
        if (this.recorder?.state === 'recording') {
            this.recorder.stop(); // onstop hands the file over and closes the camera.
        }
    },

    closeCamera() {
        clearInterval(this.timer);
        this.recording = false;
        this.stream?.getTracks().forEach((track) => track.stop());
        this.stream = null;
        this.camera = false;
    },

    recordLabel() {
        const clock = (s) => `${Math.floor(s / 60)}:${String(s % 60).padStart(2, '0')}`;
        return `${clock(this.recordedSeconds)} / ${clock(maxSeconds)}`;
    },

    // ---- Trimmer -------------------------------------------------------------------------------

    hasTrimmer() {
        return this.previewUrl !== null && this.duration !== null && !this.camera;
    },

    trimPercent(seconds) {
        return this.duration ? (seconds / this.duration) * 100 : 0;
    },

    trimLabel() {
        const clock = (s) => `${Math.floor(s / 60)}:${String(Math.floor(s % 60)).padStart(2, '0')}`;
        return `${clock(this.trimStart)} – ${clock(this.trimEnd)} · ${Math.round(this.trimEnd - this.trimStart)} ${i18n.secondsShort}`;
    },

    // The server only gets a cut when the user actually shortened the video.
    syncTrim() {
        const cut = this.duration !== null && (this.trimStart > 0.05 || this.trimEnd < this.duration - 0.05);
        this.$wire.set('trimStartMs', cut ? Math.round(this.trimStart * 1000) : null, false);
        this.$wire.set('trimEndMs', cut ? Math.round(this.trimEnd * 1000) : null, false);
    },

    // Drag a handle ('start' | 'end'). The kept part stays between 1 s and maxSeconds:
    // pulling one handle past those limits drags the other one along.
    startTrimDrag(event, handle) {
        const track = this.$refs.trimTrack;
        const move = (e) => {
            const rect = track.getBoundingClientRect();
            const t = Math.min(1, Math.max(0, (e.clientX - rect.left) / Math.max(1, rect.width))) * this.duration;
            if (handle === 'start') {
                this.trimStart = Math.min(t, this.duration - 1);
                this.trimEnd = Math.min(this.duration, Math.max(this.trimEnd, this.trimStart + 1), this.trimStart + maxSeconds);
            } else {
                this.trimEnd = Math.max(t, 1);
                this.trimStart = Math.max(0, Math.min(this.trimStart, this.trimEnd - 1), this.trimEnd - maxSeconds);
            }
            const preview = this.$refs.preview;
            if (preview) {
                preview.currentTime = handle === 'start' ? this.trimStart : Math.max(this.trimStart, this.trimEnd - 0.5);
            }
        };
        const stop = () => {
            window.removeEventListener('pointermove', move);
            window.removeEventListener('pointerup', stop);
            this.dirty = true;
            this.syncTrim();
        };
        move(event);
        window.addEventListener('pointermove', move);
        window.addEventListener('pointerup', stop);
    },

    // Loop the preview inside the kept part.
    keepInTrim(video) {
        if (this.duration === null) {
            return;
        }
        if (video.currentTime < this.trimStart - 0.1 || video.currentTime > this.trimEnd) {
            video.currentTime = this.trimStart;
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
        if (file) {
            await this.useFile(file);
        }
    },

    async useFile(file) {
        this.error = '';
        if (file.size > maxBytes) {
            this.error = i18n.tooBig;
            return;
        }
        // Some containers/codecs expose no readable duration in the browser; the server's ffprobe decides then.
        // A longer video is fine: the trimmer starts on its first maxSeconds and the user picks the part to keep.
        const duration = await readDuration(file);
        this.duration = Number.isFinite(duration) && duration > 0 ? duration : null;
        this.trimStart = 0;
        this.trimEnd = this.duration !== null ? Math.min(this.duration, maxSeconds) : 0;
        this.syncTrim();

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
