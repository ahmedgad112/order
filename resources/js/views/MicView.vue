<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { Megaphone, Mic, MicOff, Pencil, Play, Plus, Radio, Send, ShieldAlert, Square, Trash2 } from 'lucide-vue-next';
import { axios } from '../bootstrap';
import AppNavbar from '../components/AppNavbar.vue';

const mode = ref('record'); // 'record' | 'live' | 'text'
const talking = ref(false);
const micError = ref('');
const chunksSent = ref(0);

const recording = ref(false);
const recordingSeconds = ref(0);
const recordedUrl = ref('');
const sending = ref(false);
const sendSuccess = ref('');

const announceText = ref('');
const announceVoice = ref('ar-EG-SalmaNeural');
const announceRate = ref('0%');
const announceLoading = ref(false);

const presets = ref([]);
const showPresetForm = ref(false);
const editingPreset = ref(null);
const presetForm = ref({ label: '', text: '' });
const presetSaving = ref(false);
const presetError = ref('');
const presetSendingId = ref(null);

const voiceOptions = [
    { value: 'ar-EG-SalmaNeural', label: 'عربي مصري — سلمى (أنثى)' },
    { value: 'ar-EG-ShakirNeural', label: 'عربي مصري — شاكر (ذكر)' },
    { value: 'ar-SA-ZariyahNeural', label: 'عربي فصحى — زرية (أنثى)' },
    { value: 'ar-SA-HamedNeural', label: 'عربي فصحى — حامد (ذكر)' },
];

const rateOptions = [
    { value: '-25%', label: 'بطيء' },
    { value: '0%', label: 'عادي' },
    { value: '+25%', label: 'سريع' },
    { value: '+50%', label: 'سريع جداً' },
];

const supported = computed(() => (
    'mediaDevices' in navigator
    && 'getUserMedia' in navigator.mediaDevices
    && 'MediaRecorder' in window
));

let stream = null;
let recorder = null;
let sessionId = '';
let sequence = 0;
let uploadChain = Promise.resolve();
let recordedBlob = null;
let recordedChunks = [];
let recordTimer = null;

function pickMimeType() {
    const candidates = [
        'audio/webm;codecs=opus',
        'audio/webm',
        'audio/mp4',
        'audio/ogg;codecs=opus',
    ];

    return candidates.find((type) => MediaRecorder.isTypeSupported(type)) ?? '';
}

function extensionFor(mimeType) {
    if (mimeType?.includes('mp4')) {
        return 'm4a';
    }

    if (mimeType?.includes('ogg')) {
        return 'ogg';
    }

    return 'webm';
}

async function acquireStream() {
    if (!supported.value) {
        micError.value = 'المتصفح لا يدعم تسجيل الصوت.';
        return false;
    }

    try {
        if (!stream) {
            stream = await navigator.mediaDevices.getUserMedia({
                audio: { echoCancellation: true, noiseSuppression: true },
            });
        }
    } catch {
        micError.value = 'تعذر الوصول إلى الميكروفون. امنح الإذن للمتصفح، وتأكد أن الصفحة تعمل عبر HTTPS أو localhost.';
        return false;
    }

    return true;
}

// ---- Live push-to-talk ----

function queueUpload(blob, final) {
    const seq = sequence;
    sequence += 1;

    uploadChain = uploadChain.then(async () => {
        const form = new FormData();
        form.append('session_id', sessionId);
        form.append('seq', String(seq));

        if (blob) {
            form.append('audio', blob, `chunk.${extensionFor(recorder?.mimeType)}`);
        }

        if (final) {
            form.append('final', '1');
        }

        try {
            await axios.post('/admin/mic-chunk', form);
            chunksSent.value += 1;
        } catch {
            // drop failed chunks; the next ones still play
        }
    });
}

async function startTalking() {
    micError.value = '';

    if (!await acquireStream()) {
        return;
    }

    sessionId = crypto.randomUUID();
    sequence = 0;

    const mimeType = pickMimeType();
    recorder = new MediaRecorder(stream, mimeType ? { mimeType } : undefined);
    recorder.ondataavailable = (event) => {
        if (event.data?.size) {
            queueUpload(event.data, false);
        }
    };
    recorder.onstop = () => queueUpload(null, true);
    recorder.start(500);
    talking.value = true;
}

function stopTalking() {
    if (recorder && recorder.state !== 'inactive') {
        recorder.stop();
    }

    talking.value = false;
}

// ---- Record then send ----

async function startRecording() {
    micError.value = '';
    sendSuccess.value = '';

    if (!await acquireStream()) {
        return;
    }

    discardRecording(false);

    const mimeType = pickMimeType();
    recordedChunks = [];

    recorder = new MediaRecorder(stream, mimeType ? { mimeType } : undefined);
    recorder.ondataavailable = (event) => {
        if (event.data?.size) {
            recordedChunks.push(event.data);
        }
    };
    recorder.onstop = () => {
        recordedBlob = new Blob(recordedChunks, { type: recorder?.mimeType || 'audio/webm' });
        recordedUrl.value = URL.createObjectURL(recordedBlob);
        recording.value = false;
    };

    recordingSeconds.value = 0;
    recordTimer = setInterval(() => {
        recordingSeconds.value += 1;
    }, 1000);

    recorder.start();
    recording.value = true;
}

function stopRecording() {
    if (recordTimer) {
        clearInterval(recordTimer);
        recordTimer = null;
    }

    if (recorder && recorder.state !== 'inactive') {
        recorder.stop();
    }

    recording.value = false;
}

function discardRecording(resetSeconds = true) {
    if (recordedUrl.value) {
        URL.revokeObjectURL(recordedUrl.value);
    }

    recordedUrl.value = '';
    recordedBlob = null;
    recordedChunks = [];

    if (resetSeconds) {
        recordingSeconds.value = 0;
    }
}

async function sendRecording() {
    if (!recordedBlob || sending.value) {
        return;
    }

    sending.value = true;
    micError.value = '';
    sendSuccess.value = '';

    const form = new FormData();
    form.append('audio', recordedBlob, `recording.${extensionFor(recordedBlob.type)}`);

    try {
        const { data } = await axios.post('/admin/mic-recording', form);
        sendSuccess.value = data?.message || 'تم إرسال التسجيل إلى شاشة العرض.';
        discardRecording();
    } catch {
        micError.value = 'تعذر إرسال التسجيل. حاول مرة أخرى.';
    } finally {
        sending.value = false;
    }
}

// ---- Text announcement ----

async function sendTextAnnouncement() {
    const text = announceText.value.trim();

    if (text.length < 2) {
        micError.value = 'اكتب نص الإعلان أولاً.';
        return;
    }

    announceLoading.value = true;
    micError.value = '';
    sendSuccess.value = '';

    try {
        const { data } = await axios.post('/admin/announce', {
            text,
            voice: announceVoice.value,
            rate: announceRate.value,
        });
        sendSuccess.value = data?.message || 'تم إرسال الإعلان الصوتي.';
        announceText.value = '';
    } catch (err) {
        micError.value = err.response?.data?.message
            ?? Object.values(err.response?.data?.errors ?? {}).flat()[0]
            ?? 'تعذر إرسال الإعلان.';
    } finally {
        announceLoading.value = false;
    }
}

// ---- Saved messages (presets) ----

async function loadPresets() {
    try {
        const { data } = await axios.get('/admin/announcement-presets');
        presets.value = data.presets ?? [];
    } catch {
        presets.value = [];
    }
}

function openPresetForm(preset = null) {
    editingPreset.value = preset;
    presetForm.value = preset
        ? { label: preset.label, text: preset.text }
        : { label: '', text: announceText.value.trim() };
    presetError.value = '';
    showPresetForm.value = true;
}

function closePresetForm() {
    showPresetForm.value = false;
    editingPreset.value = null;
    presetForm.value = { label: '', text: '' };
    presetError.value = '';
}

async function savePreset() {
    presetSaving.value = true;
    presetError.value = '';

    try {
        const payload = {
            label: presetForm.value.label.trim(),
            text: presetForm.value.text.trim(),
        };
        const { data } = editingPreset.value
            ? await axios.put(`/admin/announcement-presets/${editingPreset.value.id}`, payload)
            : await axios.post('/admin/announcement-presets', payload);
        presets.value = data.presets ?? [];
        closePresetForm();
    } catch (err) {
        presetError.value = Object.values(err.response?.data?.errors ?? {}).flat()[0]
            ?? err.response?.data?.message
            ?? 'تعذر حفظ الرسالة.';
    } finally {
        presetSaving.value = false;
    }
}

async function deletePreset(preset) {
    if (!confirm(`حذف الرسالة "${preset.label}"؟`)) {
        return;
    }

    try {
        const { data } = await axios.delete(`/admin/announcement-presets/${preset.id}`);
        presets.value = data.presets ?? [];
    } catch {
        micError.value = 'تعذر حذف الرسالة.';
    }
}

async function sendPreset(preset) {
    presetSendingId.value = preset.id;
    micError.value = '';
    sendSuccess.value = '';

    try {
        const { data } = await axios.post('/admin/announce', {
            text: preset.text,
            voice: announceVoice.value,
            rate: announceRate.value,
        });
        sendSuccess.value = data?.message || 'تم إرسال الإعلان الصوتي.';
    } catch {
        micError.value = 'تعذر إرسال الإعلان.';
    } finally {
        presetSendingId.value = null;
    }
}

function formatSeconds(total) {
    const minutes = Math.floor(total / 60);
    const seconds = total % 60;

    return `${minutes}:${String(seconds).padStart(2, '0')}`;
}

function switchMode(next) {
    if (mode.value === next) {
        return;
    }

    stopTalking();
    stopRecording();
    discardRecording();
    micError.value = '';
    sendSuccess.value = '';
    mode.value = next;
}

function releaseStream() {
    stream?.getTracks().forEach((track) => track.stop());
    stream = null;
}

onMounted(() => {
    // Request mic access up front so pressing the button starts instantly.
    acquireStream();
    loadPresets();
});

onUnmounted(() => {
    stopTalking();
    stopRecording();
    discardRecording();
    releaseStream();
});
</script>

<template>
    <div class="flex min-h-screen flex-col bg-slate-100">
        <AppNavbar title="الميكروفون" subtitle="تحدث من هاتفك ليصدر صوتك على شاشة العرض" />

        <main class="flex flex-1 items-center justify-center p-6">
            <div class="w-full max-w-md rounded-3xl border border-slate-200 bg-white p-8 text-center shadow-sm">
                <div class="mx-auto mb-2 grid w-full grid-cols-3 gap-1 rounded-full border border-slate-200 bg-slate-50 p-1 text-sm font-bold">
                    <button
                        type="button"
                        class="rounded-full px-2 py-2 transition-colors"
                        :class="mode === 'record' ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-500 hover:text-slate-700'"
                        @click="switchMode('record')"
                    >
                        تسجيل ثم إرسال
                    </button>
                    <button
                        type="button"
                        class="rounded-full px-2 py-2 transition-colors"
                        :class="mode === 'live' ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-500 hover:text-slate-700'"
                        @click="switchMode('live')"
                    >
                        بث مباشر
                    </button>
                    <button
                        type="button"
                        class="rounded-full px-2 py-2 transition-colors"
                        :class="mode === 'text' ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-500 hover:text-slate-700'"
                        @click="switchMode('text')"
                    >
                        إعلان نصي
                    </button>
                </div>

                <p
                    v-if="mode !== 'text'"
                    class="mb-5 rounded-2xl border border-indigo-100 bg-indigo-50 px-3 py-2 text-xs font-semibold leading-5 text-indigo-800"
                >
                    ننصح باستخدام «تسجيل ثم إرسال»: تسجّل إعلانك وتستمع إليه قبل إرساله، فيظهر بأفضل جودة على الشاشة. البث المباشر قد يتأثر بجودة الشبكة.
                </p>
                <div v-else class="mb-5" />

                <div
                    class="mx-auto mb-6 flex h-28 w-28 items-center justify-center rounded-full transition-colors"
                    :class="(talking || recording) ? 'bg-red-100 text-red-600' : 'bg-indigo-100 text-indigo-600'"
                >
                    <Mic v-if="supported" class="h-12 w-12" :class="{ 'animate-pulse': talking || recording }" />
                    <MicOff v-else class="h-12 w-12" />
                </div>

                <template v-if="mode === 'live'">
                    <h2 class="text-xl font-black text-slate-900">
                        {{ talking ? 'جاري البث...' : 'اضغط مطولاً للتحدث' }}
                    </h2>
                    <p class="mt-2 text-sm text-slate-500">
                        استمر بالضغط على الزر أثناء الكلام، واتركه عند الانتهاء.
                        سيُسمع صوتك على شاشة العرض مباشرة.
                    </p>

                    <button
                        type="button"
                        class="mt-8 flex w-full select-none items-center justify-center gap-3 rounded-3xl px-6 py-6 text-xl font-black text-white transition-colors"
                        :class="talking ? 'bg-red-600' : 'bg-indigo-600 hover:bg-indigo-700'"
                        :disabled="!supported"
                        @pointerdown.prevent="startTalking"
                        @pointerup.prevent="stopTalking"
                        @pointerleave.prevent="stopTalking"
                        @pointercancel.prevent="stopTalking"
                        @contextmenu.prevent
                    >
                        <Radio class="h-6 w-6" :class="{ 'animate-pulse': talking }" />
                        {{ talking ? 'اترك للإيقاف' : 'اضغط وتحدث' }}
                    </button>

                    <p v-if="chunksSent > 0" class="mt-4 text-xs font-semibold text-slate-400">
                        تم إرسال {{ chunksSent }} مقطع صوتي
                    </p>
                </template>

                <template v-else-if="mode === 'record'">
                    <h2 class="text-xl font-black text-slate-900">
                        {{ recording ? `جاري التسجيل... ${formatSeconds(recordingSeconds)}` : 'سجّل إعلانك ثم أرسله' }}
                    </h2>
                    <p class="mt-2 text-sm text-slate-500">
                        اضغط «تسجيل» وتحدث، ثم «إيقاف». استمع للتسجيل وأرسله — سيُسمع على شاشة العرض بعد الإرسال.
                    </p>

                    <button
                        v-if="!recordedUrl"
                        type="button"
                        class="mt-8 flex w-full items-center justify-center gap-3 rounded-3xl px-6 py-6 text-xl font-black text-white transition-colors"
                        :class="recording ? 'bg-red-600 hover:bg-red-700' : 'bg-indigo-600 hover:bg-indigo-700'"
                        :disabled="!supported"
                        @click="recording ? stopRecording() : startRecording()"
                    >
                        <Square v-if="recording" class="h-6 w-6" />
                        <Mic v-else class="h-6 w-6" />
                        {{ recording ? 'إيقاف التسجيل' : 'تسجيل' }}
                    </button>

                    <div v-else class="mt-8 space-y-4">
                        <audio :src="recordedUrl" controls class="w-full">
                            <Play class="h-5 w-5" />
                        </audio>

                        <div class="grid grid-cols-2 gap-3">
                            <button
                                type="button"
                                class="flex items-center justify-center gap-2 rounded-2xl bg-indigo-600 px-4 py-4 text-lg font-black text-white transition-colors hover:bg-indigo-700 disabled:opacity-50"
                                :disabled="sending"
                                @click="sendRecording"
                            >
                                <Send class="h-5 w-5" />
                                {{ sending ? 'جاري الإرسال...' : 'إرسال للعرض' }}
                            </button>
                            <button
                                type="button"
                                class="flex items-center justify-center gap-2 rounded-2xl border border-slate-300 bg-white px-4 py-4 text-lg font-black text-slate-700 transition-colors hover:bg-slate-50"
                                :disabled="sending"
                                @click="discardRecording(); startRecording()"
                            >
                                <Trash2 class="h-5 w-5" />
                                إعادة
                            </button>
                        </div>
                    </div>
                </template>

                <template v-else>
                    <h2 class="text-xl font-black text-slate-900">إعلان نصي</h2>
                    <p class="mt-2 text-sm text-slate-500">
                        اكتب رسالة وسيتم نطقها بصوت آلي على شاشة العرض مباشرة.
                    </p>

                    <textarea
                        v-model="announceText"
                        rows="3"
                        maxlength="500"
                        class="mt-6 w-full rounded-2xl border border-slate-200 px-4 py-3 text-right outline-none focus:border-indigo-400 focus:ring-4 focus:ring-indigo-100"
                        placeholder="مثال: على المتقدمين لكلية الهندسة التوجه إلى الطابق الأول"
                    />

                    <div class="mt-3 grid gap-3 sm:grid-cols-2">
                        <div class="text-right">
                            <label class="mb-1 block text-sm font-semibold text-slate-700">الصوت</label>
                            <select
                                v-model="announceVoice"
                                class="w-full rounded-xl border border-slate-200 px-4 py-2.5 outline-none focus:border-indigo-400 focus:ring-4 focus:ring-indigo-100"
                            >
                                <option v-for="voice in voiceOptions" :key="voice.value" :value="voice.value">
                                    {{ voice.label }}
                                </option>
                            </select>
                        </div>
                        <div class="text-right">
                            <label class="mb-1 block text-sm font-semibold text-slate-700">سرعة الكلام</label>
                            <select
                                v-model="announceRate"
                                class="w-full rounded-xl border border-slate-200 px-4 py-2.5 outline-none focus:border-indigo-400 focus:ring-4 focus:ring-indigo-100"
                            >
                                <option v-for="rate in rateOptions" :key="rate.value" :value="rate.value">
                                    {{ rate.label }}
                                </option>
                            </select>
                        </div>
                    </div>

                    <button
                        type="button"
                        class="mt-5 flex w-full items-center justify-center gap-3 rounded-3xl bg-indigo-600 px-6 py-4 text-lg font-black text-white transition-colors hover:bg-indigo-700 disabled:opacity-50"
                        :disabled="announceLoading"
                        @click="sendTextAnnouncement"
                    >
                        <Megaphone class="h-5 w-5" />
                        {{ announceLoading ? 'جاري الإرسال...' : 'إرسال الإعلان' }}
                    </button>

                    <div class="mt-6 border-t border-slate-200 pt-4 text-right">
                        <div class="mb-2 flex items-center justify-between">
                            <h3 class="text-sm font-bold text-slate-700">رسائل جاهزة</h3>
                            <button
                                type="button"
                                class="flex items-center gap-1 text-xs font-semibold text-indigo-600 hover:text-indigo-800"
                                @click="showPresetForm ? closePresetForm() : openPresetForm()"
                            >
                                <Plus class="h-3.5 w-3.5" />
                                حفظ رسالة جاهزة
                            </button>
                        </div>

                        <ul v-if="presets.length" class="space-y-2">
                            <li
                                v-for="preset in presets"
                                :key="preset.id"
                                class="flex items-center gap-1.5 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2"
                            >
                                <button
                                    type="button"
                                    class="min-w-0 flex-1 text-right"
                                    title="استخدام في مربع النص"
                                    @click="announceText = preset.text"
                                >
                                    <span class="block truncate text-sm font-semibold text-slate-800">{{ preset.label }}</span>
                                    <span class="block truncate text-xs text-slate-500">{{ preset.text }}</span>
                                </button>
                                <button
                                    type="button"
                                    class="shrink-0 rounded-lg bg-indigo-600 p-2 text-white hover:bg-indigo-700 disabled:opacity-50"
                                    :disabled="presetSendingId === preset.id"
                                    title="إرسال فوري"
                                    @click="sendPreset(preset)"
                                >
                                    <Megaphone class="h-4 w-4" />
                                </button>
                                <button
                                    type="button"
                                    class="shrink-0 rounded-lg border border-slate-200 bg-white p-2 text-slate-600 hover:bg-slate-100"
                                    title="تعديل"
                                    @click="openPresetForm(preset)"
                                >
                                    <Pencil class="h-4 w-4" />
                                </button>
                                <button
                                    type="button"
                                    class="shrink-0 rounded-lg border border-red-200 bg-white p-2 text-red-600 hover:bg-red-50"
                                    title="حذف"
                                    @click="deletePreset(preset)"
                                >
                                    <Trash2 class="h-4 w-4" />
                                </button>
                            </li>
                        </ul>
                        <p v-else class="text-xs text-slate-400">لا توجد رسائل محفوظة — احفظ رسالة تعيد استخدامها.</p>

                        <div
                            v-if="showPresetForm"
                            class="mt-3 space-y-3 rounded-2xl border border-indigo-100 bg-indigo-50/50 p-3"
                        >
                            <div>
                                <label class="mb-1 block text-xs font-semibold text-slate-600">اسم الرسالة</label>
                                <input
                                    v-model="presetForm.label"
                                    type="text"
                                    maxlength="80"
                                    class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm outline-none focus:border-indigo-400"
                                    placeholder="مثال: نداء استراحة"
                                />
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-semibold text-slate-600">نص الرسالة</label>
                                <textarea
                                    v-model="presetForm.text"
                                    rows="2"
                                    maxlength="500"
                                    class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm outline-none focus:border-indigo-400"
                                    placeholder="النص الذي سيُنطق على الشاشة"
                                />
                            </div>
                            <p v-if="presetError" class="rounded-xl bg-red-50 px-3 py-2 text-xs text-red-700">
                                {{ presetError }}
                            </p>
                            <div class="flex gap-2">
                                <button
                                    type="button"
                                    class="flex-1 rounded-xl bg-indigo-600 py-2 text-sm font-bold text-white hover:bg-indigo-700 disabled:opacity-60"
                                    :disabled="presetSaving"
                                    @click="savePreset"
                                >
                                    {{ presetSaving ? 'جاري الحفظ...' : (editingPreset ? 'حفظ التعديل' : 'حفظ الرسالة') }}
                                </button>
                                <button
                                    type="button"
                                    class="flex-1 rounded-xl border border-slate-200 py-2 text-sm font-semibold text-slate-700 hover:bg-white"
                                    @click="closePresetForm"
                                >
                                    إلغاء
                                </button>
                            </div>
                        </div>
                    </div>
                </template>

                <p v-if="sendSuccess" class="mt-4 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-700">
                    {{ sendSuccess }}
                </p>

                <div
                    v-if="micError"
                    class="mt-4 flex items-start gap-2 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-right text-sm text-red-700"
                >
                    <ShieldAlert class="mt-0.5 h-4 w-4 shrink-0" />
                    <span>{{ micError }}</span>
                </div>

                <div
                    v-if="!supported"
                    class="mt-4 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800"
                >
                    تسجيل الصوت يتطلب متصفحاً حديثاً وصفحة تعمل عبر HTTPS أو localhost.
                </div>
            </div>
        </main>
    </div>
</template>
