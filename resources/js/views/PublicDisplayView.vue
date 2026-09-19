<script setup>
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { CheckCircle2, Clock, Lock, Megaphone, Mic, Ticket, Users, Volume2, VolumeX } from 'lucide-vue-next';
import { useQueueStore } from '../stores/queueStore';
import AppNavbar from '../components/AppNavbar.vue';

const queueStore = useQueueStore();
const clock = ref(formatClock());
const today = ref(formatDate());
const announcedCallKeys = new Set();
let callsSeeded = false;
const voiceEnabled = ref(localStorage.getItem('display_voice') !== '0');
const liveMic = ref(false);
const soundReady = ref(false);
const lastCall = ref(null);
const lastCalledId = ref(null);
const footerAnnouncement = ref('');
let micPending = 0;
let sharedAudioCtx = null;
let announcementTimer = null;
const seenAnnouncementKeys = new Set();
let announcementsSeeded = false;

const SILENT_WAV = 'data:audio/wav;base64,UklGRiQAAABXQVZFZm10IBAAAAABAAEAQB8AAIA+AAACABAAZGF0YQAAAAA=';
const CHIME_URL = '/audio/airport-ding.wav';

function probeAudio() {
    const probe = new Audio(SILENT_WAV);
    probe.play()
        .then(() => { soundReady.value = true; })
        .catch(() => { soundReady.value = false; });
}

async function enableSound() {
    try {
        sharedAudioCtx ??= new (window.AudioContext || window.webkitAudioContext)();
        await sharedAudioCtx.resume();
        await new Audio(SILENT_WAV).play();
        soundReady.value = true;
    } catch {
        soundReady.value = false;
    }
}
let clockTimer = null;
let unsubscribeEcho = null;
let stopAutoRefresh = null;

const audioQueue = [];
let audioPlaying = false;

function enqueueAudio(url, kind = 'tts') {
    if (!url) {
        return;
    }

    audioQueue.push({ url, kind });

    if (kind === 'mic') {
        micPending += 1;
        liveMic.value = true;
    }

    playNextAudio();
}

function playNextAudio() {
    if (audioPlaying || audioQueue.length === 0) {
        return;
    }

    const item = audioQueue.shift();
    audioPlaying = true;

    const audio = new Audio(item.url);
    const done = () => {
        audioPlaying = false;

        if (item.kind === 'mic') {
            micPending -= 1;
            if (micPending <= 0) {
                micPending = 0;
                liveMic.value = false;
            }
        }

        playNextAudio();
    };

    audio.onended = done;
    audio.onerror = done;
    audio.play().catch(done);
}

// ---- Live mic stream (MediaSource) ----

let micSource = null;
let micBuffer = null;
let micEl = null;
let micMime = '';
let micAppending = false;
const micAppendQueue = [];
let micIdleTimer = null;

const MIC_MIME_BY_EXT = {
    webm: 'audio/webm;codecs="opus"',
    ogg: 'audio/ogg;codecs="opus"',
    m4a: 'audio/mp4',
    mp4: 'audio/mp4',
    mp3: 'audio/mpeg',
};

function micMimeFor(url) {
    const ext = String(url).split('?')[0].split('.').pop().toLowerCase();
    return MIC_MIME_BY_EXT[ext] ?? '';
}

function resetMicStream() {
    micAppendQueue.length = 0;
    micAppending = false;
    micBuffer = null;
    micMime = '';

    if (micEl) {
        try {
            micEl.pause();
        } catch { /* noop */ }
        if (micEl.src?.startsWith('blob:')) {
            URL.revokeObjectURL(micEl.src);
        }
    }

    micEl = null;
    micSource = null;
}

function ensureMicStream(mime) {
    if (micSource && micMime === mime) {
        return;
    }

    resetMicStream();
    micMime = mime;
    micSource = new MediaSource();
    micEl = new Audio();
    micEl.src = URL.createObjectURL(micSource);
    micEl.play().catch(() => {});

    micSource.addEventListener('sourceopen', () => {
        try {
            micBuffer = micSource.addSourceBuffer(mime);
            micBuffer.mode = 'sequence';

            const onDone = () => {
                micAppending = false;
                flushMicAppends();
            };

            micBuffer.addEventListener('updateend', onDone);
            micBuffer.addEventListener('error', onDone);
            micBuffer.addEventListener('abort', onDone);
            flushMicAppends();
        } catch {
            resetMicStream();
        }
    }, { once: true });
}

function flushMicAppends() {
    if (micAppending || !micBuffer || micAppendQueue.length === 0) {
        return;
    }

    const item = micAppendQueue[0];

    if (item.mime !== micMime) {
        return;
    }

    micAppendQueue.shift();
    micAppending = true;

    try {
        micBuffer.appendBuffer(item.bytes);
    } catch {
        micAppending = false;
        resetMicStream();
        return;
    }

    if (micEl?.paused) {
        micEl.play().catch(() => {});
    }
}

function armMicIdle(delay = 6000) {
    clearTimeout(micIdleTimer);
    micIdleTimer = setTimeout(() => {
        liveMic.value = false;
    }, delay);
}

// ---- /Live mic stream ----

function toggleVoice() {
    voiceEnabled.value = !voiceEnabled.value;
    localStorage.setItem('display_voice', voiceEnabled.value ? '1' : '0');
}

function formatClock() {
    return new Date().toLocaleTimeString('ar-EG', {
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
    });
}

function formatDate() {
    return new Date().toLocaleDateString('ar-EG', {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
    });
}

function formatTicketNumber(number) {
    return String(number ?? '');
}

function displayName(ticket) {
    return ticket.full_name || ticket.masked_name || '';
}

function playChime() {
    enqueueAudio(CHIME_URL, 'chime');
}

function callKey(ticket) {
    return `${ticket.id}:${ticket.called_at ?? ''}`;
}

function noteCall(ticket) {
    announcedCallKeys.add(callKey(ticket));

    if (announcedCallKeys.size > 60) {
        announcedCallKeys.delete(announcedCallKeys.values().next().value);
    }
}

async function announceCall(ticket, step = null, muted = false, counter = null) {
    lastCalledId.value = ticket.id;
    lastCall.value = {
        number: ticket.ticket_number,
        name: displayName(ticket),
        counter: counter || ticket.counter_name || ticket.teller_name || '',
    };

    if (muted) {
        return;
    }

    playChime();

    if (voiceEnabled.value) {
        try {
            const audioUrl = await queueStore.requestTicketAudio(ticket.id, step);

            if (audioUrl) {
                enqueueAudio(audioUrl);
            }
        } catch {
            // TTS unavailable — chime already played
        }
    }
}

async function onTicketCalled(event) {
    const ticket = event.ticket;

    if (!ticket?.id || announcedCallKeys.has(callKey(ticket))) {
        return;
    }

    noteCall(ticket);
    await announceCall(ticket, event.step ?? null, Boolean(event.muted), event.counter ?? null);
}

function onAnnouncement(event) {
    enqueueAudio(event.audio_url);

    if (event.text) {
        footerAnnouncement.value = event.text;
        clearTimeout(announcementTimer);
        announcementTimer = setTimeout(() => {
            footerAnnouncement.value = '';
        }, 12000);
    }
}

function announcementKeys(event) {
    const keys = [];

    if (event?.id != null) {
        keys.push(`id:${event.id}`);
    }

    if (event?.audio_url) {
        keys.push(`audio:${event.audio_url}`);
    }

    return keys;
}

function rememberAnnouncement(event) {
    announcementKeys(event).forEach((key) => seenAnnouncementKeys.add(key));
}

function consumeAnnouncement(event) {
    if (!event || (!event.id && !event.audio_url && !event.text)) {
        return;
    }

    if (announcementKeys(event).some((key) => seenAnnouncementKeys.has(key))) {
        rememberAnnouncement(event);
        return;
    }

    rememberAnnouncement(event);
    onAnnouncement(event);
}

function onMicChunk(event) {
    if (event.final && !event.audio_url) {
        armMicIdle(2500);
        return;
    }

    const streamUrl = event.raw_url || event.audio_url;

    if (!streamUrl) {
        return;
    }

    const mime = micMimeFor(streamUrl);
    const canStream = 'MediaSource' in window
        && mime
        && MediaSource.isTypeSupported(mime);

    if (!canStream) {
        enqueueAudio(event.audio_url || streamUrl, 'mic');
        return;
    }

    liveMic.value = true;
    armMicIdle();

    fetch(streamUrl)
        .then((res) => (res.ok ? res.arrayBuffer() : Promise.reject()))
        .then((bytes) => {
            if (bytes.byteLength === 0) {
                return;
            }
            micAppendQueue.push({ bytes, mime });
            ensureMicStream(mime);
            flushMicAppends();
            armMicIdle();
        })
        .catch(() => {
            // dropped chunk — stream continues
        });
}

const remainingWaitingCount = computed(() => (
    Math.max(0, (queueStore.stats.waiting ?? 0) - queueStore.waiting.length)
));

const servingGridClass = computed(() => {
    const count = queueStore.serving.length;

    if (count <= 1) {
        return 'grid-cols-1';
    }

    if (count === 2) {
        return 'grid-cols-1 md:grid-cols-2';
    }

    return 'grid-cols-1 md:grid-cols-2 xl:grid-cols-3';
});

watch(() => queueStore.serving, (list) => {
    if (!Array.isArray(list)) {
        return;
    }

    if (!callsSeeded) {
        list.forEach(noteCall);
        callsSeeded = true;

        const latest = list
            .filter((ticket) => ticket?.called_at)
            .sort((a, b) => String(a.called_at).localeCompare(String(b.called_at)))
            .at(-1);

        if (latest && !lastCall.value) {
            lastCalledId.value = latest.id;
            lastCall.value = {
                number: latest.ticket_number,
                name: displayName(latest),
                counter: latest.counter_name || latest.teller_name || '',
            };
        }

        return;
    }

    const fresh = list.filter((ticket) => ticket?.id && !announcedCallKeys.has(callKey(ticket)));

    if (fresh.length === 0) {
        return;
    }

    fresh.forEach(noteCall);
    fresh.sort((a, b) => String(b.called_at ?? '').localeCompare(String(a.called_at ?? '')));
    announceCall(fresh[0]);
});

watch(() => queueStore.announcement, (event) => {
    if (!announcementsSeeded) {
        if (event) {
            rememberAnnouncement(event);
        }

        return;
    }

    consumeAnnouncement(event);
});

onMounted(async () => {
    await queueStore.fetchPublicStatus();
    if (queueStore.announcement) {
        rememberAnnouncement(queueStore.announcement);
    }
    announcementsSeeded = true;
    probeAudio();

    clockTimer = setInterval(() => {
        clock.value = formatClock();
        today.value = formatDate();
    }, 1000);

    unsubscribeEcho = queueStore.subscribeEcho({
        TicketCalled: onTicketCalled,
        AnnouncementMade: consumeAnnouncement,
        MicAudioChunk: onMicChunk,
    });

    stopAutoRefresh = queueStore.startAutoRefresh(() => queueStore.fetchPublicStatus({ silent: true }), 4000);
});

onUnmounted(() => {
    if (clockTimer) {
        clearInterval(clockTimer);
    }
    clearTimeout(announcementTimer);
    clearTimeout(micIdleTimer);
    resetMicStream();
    unsubscribeEcho?.();
    stopAutoRefresh?.();
});
</script>

<template>
    <div class="flex min-h-screen flex-col bg-gradient-to-br from-blue-50 via-white to-indigo-50 text-slate-900">
        <button
            v-if="!soundReady"
            type="button"
            class="fixed inset-0 z-[60] flex flex-col items-center justify-center gap-6 bg-slate-900/90 text-white backdrop-blur-sm"
            @click="enableSound"
        >
            <Volume2 class="h-20 w-20 animate-pulse" />
            <span class="text-3xl font-black">اضغط لتفعيل الصوت</span>
            <span class="text-lg text-slate-300">الصوت مطلوب لسماع النداء والإعلانات على هذه الشاشة</span>
        </button>
        <AppNavbar
            title="شاشة عرض الطابور"
            subtitle="جامعة برج العرب التكنولوجية"
            variant="display"
            max-width="full"
            :show-nav="false"
        >
            <div class="flex flex-wrap items-center justify-end gap-3">
                <button
                    type="button"
                    class="flex items-center gap-2 rounded-2xl border px-3 py-2 text-sm font-bold"
                    :class="voiceEnabled
                        ? 'border-blue-200 bg-blue-50 text-blue-800'
                        : 'border-slate-200 bg-white text-slate-400'"
                    @click="toggleVoice"
                >
                    <Volume2 v-if="voiceEnabled" class="h-4 w-4" />
                    <VolumeX v-else class="h-4 w-4" />
                    نداء صوتي
                </button>
                <div
                    v-if="liveMic"
                    class="flex items-center gap-2 rounded-2xl border border-red-200 bg-red-50 px-3 py-2 text-red-700"
                >
                    <Mic class="h-4 w-4 animate-pulse" />
                    <span class="text-sm font-bold">بث مباشر</span>
                </div>
                <div
                    v-else
                    class="flex items-center gap-2 rounded-2xl border border-emerald-200 bg-emerald-50 px-3 py-2 text-emerald-800"
                >
                    <span class="h-2.5 w-2.5 animate-pulse rounded-full bg-emerald-500" />
                    <span class="text-sm font-bold">مباشر</span>
                </div>
                <div class="rounded-2xl border border-slate-200 bg-white px-4 py-2 shadow-sm">
                    <p class="text-xs font-semibold text-slate-500">{{ today }}</p>
                    <p class="flex items-center gap-2 font-mono text-xl font-black text-slate-900 sm:text-2xl">
                        <Clock class="h-5 w-5 text-blue-600" />
                        {{ clock }}
                    </p>
                </div>
            </div>
        </AppNavbar>

        <div
            v-if="!queueStore.isSystemOpen"
            class="border-b border-red-200 bg-red-50 px-8 py-4 text-center text-red-800"
        >
            <Lock class="mx-auto mb-2 h-6 w-6" />
            {{ queueStore.system.closed_message || 'النظام مغلق حالياً' }}
        </div>
        <div
            v-else-if="!queueStore.isDayOpen"
            class="border-b border-amber-200 bg-amber-50 px-8 py-4 text-center text-amber-800"
        >
            <Lock class="mx-auto mb-2 h-6 w-6" />
            {{ queueStore.system.day_ended_message || 'انتهى استقبال الطلبات اليوم' }}
        </div>

        <main class="flex flex-1 flex-col gap-6 p-4 sm:p-6 lg:p-8">
            <section class="grid gap-3 sm:grid-cols-3">
                <div class="flex items-center gap-3 rounded-2xl border border-amber-200 bg-white px-4 py-3 shadow-sm">
                    <span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-amber-100 text-amber-700">
                        <Users class="h-6 w-6" />
                    </span>
                    <div>
                        <p class="text-sm font-semibold text-slate-500">بالانتظار</p>
                        <p class="text-3xl font-black text-amber-600">{{ queueStore.stats.waiting }}</p>
                    </div>
                </div>
                <div class="flex items-center gap-3 rounded-2xl border border-blue-200 bg-white px-4 py-3 shadow-sm">
                    <span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-blue-100 text-blue-700">
                        <Volume2 class="h-6 w-6" />
                    </span>
                    <div>
                        <p class="text-sm font-semibold text-slate-500">قيد الخدمة</p>
                        <p class="text-3xl font-black text-blue-700">{{ queueStore.stats.serving }}</p>
                    </div>
                </div>
                <div class="flex items-center gap-3 rounded-2xl border border-emerald-200 bg-white px-4 py-3 shadow-sm">
                    <span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-emerald-100 text-emerald-700">
                        <CheckCircle2 class="h-6 w-6" />
                    </span>
                    <div>
                        <p class="text-sm font-semibold text-slate-500">مكتمل اليوم</p>
                        <p class="text-3xl font-black text-emerald-700">{{ queueStore.stats.completed }}</p>
                    </div>
                </div>
            </section>

            <div class="grid flex-1 gap-6 lg:grid-cols-3">
                <section class="flex flex-col lg:col-span-2">
                    <div class="mb-4 flex items-center gap-3">
                        <span class="flex h-10 w-10 items-center justify-center rounded-2xl bg-blue-600 text-white">
                            <Volume2 class="h-5 w-5" />
                        </span>
                        <div>
                            <h2 class="text-2xl font-black text-slate-900">يتم الخدمة الآن</h2>
                            <p class="text-sm text-slate-500">توجه إلى الشباك المعلن عند سماع رقمك</p>
                        </div>
                    </div>

                    <div
                        v-if="queueStore.serving.length === 0"
                        class="flex flex-1 items-center justify-center rounded-3xl border-2 border-dashed border-slate-200 bg-white/80 px-6 py-16 text-center text-lg text-slate-500"
                    >
                        لا يوجد عملاء قيد الخدمة حالياً
                    </div>

                    <div v-else class="grid flex-1 gap-4" :class="servingGridClass">
                        <article
                            v-for="ticket in queueStore.serving"
                            :key="ticket.id"
                            class="flex flex-col justify-between rounded-3xl border bg-white p-6 shadow-lg sm:p-8"
                            :class="ticket.id === lastCalledId
                                ? 'pulse-badge border-blue-400 ring-4 ring-blue-100'
                                : 'border-slate-200'"
                        >
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <p class="inline-flex rounded-full bg-blue-600 px-4 py-1.5 text-sm font-bold text-white">
                                        {{ ticket.counter_name || ticket.teller_name || 'الشباك' }}
                                    </p>
                                    <p
                                        v-if="ticket.teller_name && ticket.counter_name && ticket.teller_name !== ticket.counter_name"
                                        class="mt-2 text-sm text-slate-500"
                                    >
                                        {{ ticket.teller_name }}
                                    </p>
                                </div>
                                <span
                                    v-if="ticket.id === lastCalledId"
                                    class="rounded-full bg-blue-100 px-3 py-1 text-xs font-bold text-blue-700"
                                >
                                    تم النداء
                                </span>
                            </div>

                            <p
                                class="my-4 text-center font-black tracking-tight text-blue-700"
                                dir="ltr"
                                :class="queueStore.serving.length === 1 ? 'text-8xl sm:text-9xl' : 'text-7xl'"
                            >
                                {{ formatTicketNumber(ticket.ticket_number) }}
                            </p>

                            <div class="text-center">
                            <p v-if="displayName(ticket)" class="text-3xl font-black text-slate-800 sm:text-4xl">{{ displayName(ticket) }}</p>
                                <p v-if="ticket.request_type_label" class="mt-2 text-sm font-semibold text-slate-500">
                                    {{ ticket.request_type_label }}
                                </p>
                                <p v-if="ticket.college_label" class="text-sm text-slate-400">
                                    {{ ticket.college_label }}
                                </p>
                            </div>
                        </article>
                    </div>
                </section>

                <aside class="flex flex-col rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                    <div class="mb-4 flex items-center gap-3">
                        <span class="flex h-10 w-10 items-center justify-center rounded-2xl bg-amber-500 text-white">
                            <Ticket class="h-5 w-5" />
                        </span>
                        <div>
                            <h2 class="text-xl font-black text-slate-900">التالي في الانتظار</h2>
                            <p class="text-sm text-slate-500">{{ queueStore.stats.waiting }} تذكرة</p>
                        </div>
                    </div>

                    <ul class="flex flex-1 flex-col gap-3 overflow-y-auto">
                        <li
                            v-for="(ticket, index) in queueStore.waiting"
                            :key="ticket.id"
                            class="flex items-center justify-between gap-3 rounded-2xl border px-4 py-3"
                            :class="index === 0
                                ? 'border-amber-200 bg-amber-50'
                                : 'border-slate-100 bg-slate-50'"
                        >
                            <div class="flex min-w-0 items-center gap-3">
                                <span
                                    class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-sm font-bold"
                                    :class="index === 0 ? 'bg-amber-500 text-white' : 'bg-white text-slate-500'"
                                >
                                    {{ index + 1 }}
                                </span>
                                <div class="min-w-0">
                                    <p v-if="displayName(ticket)" class="truncate font-semibold text-slate-700">{{ displayName(ticket) }}</p>
                                    <p v-if="ticket.request_type_label" class="truncate text-xs text-slate-500">
                                        {{ ticket.request_type_label }}
                                    </p>
                                </div>
                            </div>
                            <span class="font-mono text-2xl font-black text-amber-600" dir="ltr">
                                {{ formatTicketNumber(ticket.ticket_number) }}
                            </span>
                        </li>
                        <li
                            v-if="remainingWaitingCount > 0"
                            class="rounded-2xl border border-dashed border-slate-200 py-3 text-center text-sm font-semibold text-slate-500"
                        >
                            و {{ remainingWaitingCount }} تذاكر أخرى في الانتظار
                        </li>
                        <li
                            v-if="queueStore.waiting.length === 0"
                            class="flex flex-1 flex-col items-center justify-center gap-3 py-12 text-center text-slate-400"
                        >
                            <Ticket class="h-10 w-10 text-slate-300" />
                            لا توجد تذاكر في الانتظار
                        </li>
                    </ul>
                </aside>
            </div>
        </main>

        <footer class="sticky bottom-0 border-t border-slate-200 bg-white/95 shadow-[0_-4px_20px_rgba(0,0,0,0.06)] backdrop-blur-md">
            <div class="flex items-center justify-center gap-3 px-4 py-3 sm:px-6">
                <template v-if="footerAnnouncement">
                    <Megaphone class="h-6 w-6 shrink-0 animate-pulse text-indigo-600" />
                    <p class="truncate text-lg font-bold text-indigo-800 sm:text-xl">
                        {{ footerAnnouncement }}
                    </p>
                </template>
                <template v-else-if="lastCall">
                    <Volume2 class="h-6 w-6 shrink-0 text-blue-600" />
                    <p class="text-base font-semibold text-slate-600 sm:text-lg">
                        النداء الأخير:
                        <span class="font-black text-blue-700" dir="ltr">{{ lastCall.number }}</span>
                        <span v-if="lastCall.name"> — {{ lastCall.name }}</span>
                        <span v-if="lastCall.counter"> — {{ lastCall.counter }}</span>
                    </p>
                </template>
                <p v-else class="text-sm font-semibold text-slate-400">
                    بانتظار أول نداء
                </p>
            </div>
        </footer>
    </div>
</template>
