<script setup>
import { computed, onUnmounted, ref } from 'vue';
import { Mic, MicOff, Radio, ShieldAlert } from 'lucide-vue-next';
import { axios } from '../bootstrap';
import AppNavbar from '../components/AppNavbar.vue';

const talking = ref(false);
const micError = ref('');
const chunksSent = ref(0);

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

    if (!supported.value) {
        micError.value = 'المتصفح لا يدعم تسجيل الصوت.';
        return;
    }

    try {
        if (!stream) {
            stream = await navigator.mediaDevices.getUserMedia({
                audio: { echoCancellation: true, noiseSuppression: true },
            });
        }
    } catch {
        micError.value = 'تعذر الوصول إلى الميكروفون. امنح الإذن للمتصفح، وتأكد أن الصفحة تعمل عبر HTTPS أو localhost.';
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

function releaseStream() {
    stream?.getTracks().forEach((track) => track.stop());
    stream = null;
}

onUnmounted(() => {
    stopTalking();
    releaseStream();
});
</script>

<template>
    <div class="flex min-h-screen flex-col bg-slate-100">
        <AppNavbar title="الميكروفون" subtitle="تحدث من هاتفك ليصدر صوتك على شاشة العرض" />

        <main class="flex flex-1 items-center justify-center p-6">
            <div class="w-full max-w-md rounded-3xl border border-slate-200 bg-white p-8 text-center shadow-sm">
                <div
                    class="mx-auto mb-6 flex h-28 w-28 items-center justify-center rounded-full transition-colors"
                    :class="talking ? 'bg-red-100 text-red-600' : 'bg-indigo-100 text-indigo-600'"
                >
                    <Mic v-if="supported" class="h-12 w-12" :class="{ 'animate-pulse': talking }" />
                    <MicOff v-else class="h-12 w-12" />
                </div>

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
