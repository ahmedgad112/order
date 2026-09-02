<script setup>
import { computed, defineAsyncComponent, onMounted, onUnmounted, ref } from 'vue';
import { RouterLink } from 'vue-router';
import { Ticket, Printer, User, Hash, FileText, Lock, Search, ImageDown, ExternalLink, FilePlus } from 'lucide-vue-next';
import { useQueueStore } from '../stores/queueStore';

const TicketQrCode = defineAsyncComponent(() => import('../components/TicketQrCode.vue'));

const queueStore = useQueueStore();

const form = ref({
    full_name: '',
    national_id: '',
    order_number: '',
});

const showModal = ref(false);
const issuedTicket = ref(null);
const fieldErrors = ref({});
const savingImage = ref(false);
const imageSaveError = ref('');
const ticketCaptureRef = ref(null);
let stopAutoRefresh = null;

const ticketFontFamily = '"Segoe UI", Tahoma, Arial, sans-serif';
const admissionApplyUrl = 'https://batechu.com/admission';
const admissionTrackUrl = 'https://batechu.com/admission/track';

function ticketScanUrl(ticket) {
    return `${window.location.origin}/t/${ticket.public_token}`;
}

function drawAdmissionLinkBox(ctx, width, y, label, url, background, labelColor) {
    const boxX = 28;
    const boxW = width - 56;
    const boxH = 68;

    ctx.fillStyle = background;
    ctx.beginPath();
    if (typeof ctx.roundRect === 'function') {
        ctx.roundRect(boxX, y, boxW, boxH, 14);
    } else {
        ctx.rect(boxX, y, boxW, boxH);
    }
    ctx.fill();

    ctx.direction = 'rtl';
    ctx.textAlign = 'center';
    ctx.textBaseline = 'middle';
    ctx.fillStyle = labelColor;
    ctx.font = `bold 14px ${ticketFontFamily}`;
    ctx.fillText(label, width / 2, y + 24);

    ctx.direction = 'ltr';
    ctx.fillStyle = '#334155';
    ctx.font = `11px ${ticketFontFamily}`;
    ctx.fillText(url, width / 2, y + 46);
}

async function renderTicketToCanvas(ticket) {
    const width = 400;
    const height = 720;
    const scale = 2;
    const qrSize = 168;
    const canvas = document.createElement('canvas');
    canvas.width = width * scale;
    canvas.height = height * scale;

    const ctx = canvas.getContext('2d');
    ctx.scale(scale, scale);
    ctx.direction = 'rtl';
    ctx.textAlign = 'center';
    ctx.textBaseline = 'middle';

    ctx.fillStyle = '#ffffff';
    ctx.fillRect(0, 0, width, height);

    ctx.fillStyle = '#dcfce7';
    ctx.beginPath();
    ctx.arc(width / 2, 48, 36, 0, Math.PI * 2);
    ctx.fill();

    ctx.fillStyle = '#16a34a';
    ctx.font = `bold 28px ${ticketFontFamily}`;
    ctx.fillText('✓', width / 2, 50);

    ctx.fillStyle = '#64748b';
    ctx.font = `14px ${ticketFontFamily}`;
    ctx.fillText('تم إصدار تذكرتك بنجاح', width / 2, 108);

    ctx.fillStyle = '#2563eb';
    ctx.font = `bold 68px ${ticketFontFamily}`;
    ctx.fillText(String(ticket.ticket_number), width / 2, 175);

    ctx.fillStyle = '#1e293b';
    ctx.font = `bold 18px ${ticketFontFamily}`;
    ctx.fillText(ticket.masked_name, width / 2, 228);

    const qrCanvas = document.createElement('canvas');
    const QRCode = (await import('qrcode')).default;
    await QRCode.toCanvas(qrCanvas, ticketScanUrl(ticket), {
        width: qrSize,
        margin: 1,
        color: { dark: '#0f172a', light: '#ffffff' },
    });
    ctx.setTransform(scale, 0, 0, scale, 0, 0);
    ctx.drawImage(qrCanvas, (width - qrSize) / 2, 252, qrSize, qrSize);

    ctx.direction = 'rtl';
    ctx.textAlign = 'center';
    ctx.textBaseline = 'middle';
    ctx.fillStyle = '#64748b';
    ctx.font = `13px ${ticketFontFamily}`;
    ctx.fillText('امسح الرمز لعرض بياناتك', width / 2, 448);

    ctx.fillStyle = '#94a3b8';
    ctx.font = `12px ${ticketFontFamily}`;
    ctx.fillText('يرجى الانتظار حتى يتم نداؤك', width / 2, 478);

    ctx.fillStyle = '#94a3b8';
    ctx.font = `12px ${ticketFontFamily}`;
    ctx.fillText(new Date().toLocaleString('ar-EG'), width / 2, 508);

    ctx.strokeStyle = '#e2e8f0';
    ctx.lineWidth = 1;
    ctx.beginPath();
    ctx.moveTo(36, 528);
    ctx.lineTo(width - 36, 528);
    ctx.stroke();

    drawAdmissionLinkBox(ctx, width, 542, 'تقدم الطلب', admissionApplyUrl, '#ecfdf5', '#047857');
    drawAdmissionLinkBox(ctx, width, 620, 'تتبع طلبك', admissionTrackUrl, '#eef2ff', '#4338ca');

    return canvas;
}

function downloadCanvas(canvas, filename) {
    return new Promise((resolve, reject) => {
        canvas.toBlob((blob) => {
            if (!blob) {
                reject(new Error('Failed to create image blob'));
                return;
            }

            const url = URL.createObjectURL(blob);
            const link = document.createElement('a');
            link.download = filename;
            link.href = url;
            link.style.display = 'none';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            setTimeout(() => URL.revokeObjectURL(url), 100);
            resolve();
        }, 'image/png');
    });
}

const nationalIdDisplay = computed({
    get: () => form.value.national_id,
    set: (value) => {
        form.value.national_id = value.replace(/\D/g, '').slice(0, 14);
    },
});

function validateClient() {
    const errors = {};

    if (!form.value.full_name || form.value.full_name.trim().length < 3) {
        errors.full_name = 'الاسم الكامل مطلوب (3 أحرف على الأقل).';
    }

    if (!/^\d{14}$/.test(form.value.national_id)) {
        errors.national_id = 'يجب أن يتكون الرقم القومي من 14 رقمًا.';
    }

    if (!form.value.order_number.trim()) {
        errors.order_number = 'رقم الطلب مطلوب.';
    }

    fieldErrors.value = errors;
    return Object.keys(errors).length === 0;
}

async function submitForm() {
    if (!validateClient()) {
        return;
    }

    try {
        const ticket = await queueStore.issueTicket({
            full_name: form.value.full_name.trim(),
            national_id: form.value.national_id,
            order_number: form.value.order_number.trim(),
        });

        issuedTicket.value = ticket;
        showModal.value = true;
        form.value = { full_name: '', national_id: '', order_number: '' };
        fieldErrors.value = {};
    } catch {
        fieldErrors.value.general = queueStore.error;
    }
}

function closeModal() {
    showModal.value = false;
    issuedTicket.value = null;
    imageSaveError.value = '';
}

function printTicket() {
    window.print();
}

async function saveAsImage() {
    if (!issuedTicket.value) {
        return;
    }

    savingImage.value = true;
    imageSaveError.value = '';

    try {
        const canvas = await renderTicketToCanvas(issuedTicket.value);
        const filename = `ticket-${issuedTicket.value.ticket_number}.png`;

        const isIos = /iPad|iPhone|iPod/.test(navigator.userAgent)
            || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);

        if (isIos) {
            const tab = window.open(canvas.toDataURL('image/png'), '_blank');
            if (!tab) {
                throw new Error('Popup blocked');
            }
        } else {
            await downloadCanvas(canvas, filename);
        }
    } catch {
        imageSaveError.value = 'تعذر حفظ الصورة. حاول مرة أخرى.';
    } finally {
        savingImage.value = false;
    }
}

onMounted(() => {
    queueStore.fetchPublicStatus();
    stopAutoRefresh = queueStore.startAutoRefresh(() => queueStore.fetchPublicStatus({ silent: true }), 5000);
});

onUnmounted(() => {
    stopAutoRefresh?.();
});
</script>

<template>
    <div class="min-h-screen bg-gradient-to-br from-blue-50 via-white to-indigo-50">
        <header class="border-b border-blue-100 bg-white/80 backdrop-blur">
            <div class="mx-auto flex max-w-5xl flex-col gap-4 px-4 py-5 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                <div class="flex items-center gap-3">
                    <img
                        :src="'/logo.webp'"
                        alt="جامعة برج العرب التكنولوجية"
                        class="h-14 w-auto object-contain"
                    />
                    <div>
                        <h1 class="text-2xl font-bold text-slate-900">نظام إدارة الأدوار</h1>
                        <p class="text-sm text-slate-500">إصدار تذكرة جديدة</p>
                    </div>
                </div>
                <div class="flex flex-wrap items-center gap-2 sm:gap-3">
                    <div class="flex items-center gap-3 rounded-2xl bg-blue-600 px-5 py-3 text-white shadow-lg">
                        <Ticket class="h-6 w-6" />
                        <div class="text-left">
                            <p class="text-xs opacity-80">في الانتظار</p>
                            <p class="text-2xl font-bold leading-none">{{ queueStore.stats.waiting }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <main class="mx-auto max-w-3xl px-6 py-10">
            <div
                v-if="!queueStore.isSystemOpen"
                class="mb-6 rounded-3xl border border-red-200 bg-red-50 p-6 text-center"
            >
                <Lock class="mx-auto mb-3 h-10 w-10 text-red-500" />
                <h2 class="text-xl font-bold text-red-800">النظام مغلق حالياً</h2>
                <p class="mt-2 text-red-700">{{ queueStore.system.closed_message || 'لا يمكن إصدار تذاكر جديدة في الوقت الحالي.' }}</p>
            </div>

            <div class="rounded-3xl border border-slate-200 bg-white p-8 shadow-xl" :class="{ 'opacity-60': !queueStore.isSystemOpen }">
                <h2 class="mb-8 text-center text-3xl font-bold text-slate-800">احصل على تذكرتك</h2>

                <p v-if="fieldErrors.general" class="mb-6 rounded-xl bg-red-50 px-4 py-3 text-center text-red-700">
                    {{ fieldErrors.general }}
                </p>

                <form class="space-y-6" @submit.prevent="submitForm">
                    <fieldset :disabled="!queueStore.isSystemOpen" class="space-y-6">
                    <div>
                        <label class="mb-2 flex items-center gap-2 text-sm font-semibold text-slate-700">
                            <User class="h-4 w-4" />
                            الاسم الكامل
                        </label>
                        <input
                            v-model="form.full_name"
                            type="text"
                            class="w-full rounded-2xl border border-slate-200 px-5 py-4 text-lg outline-none transition focus:border-blue-500 focus:ring-4 focus:ring-blue-100"
                            placeholder="مثال: أحمد محمود علي"
                        />
                        <p v-if="fieldErrors.full_name" class="mt-2 text-sm text-red-600">{{ fieldErrors.full_name }}</p>
                    </div>

                    <div>
                        <label class="mb-2 flex items-center gap-2 text-sm font-semibold text-slate-700">
                            <Hash class="h-4 w-4" />
                            الرقم القومي (14 رقم)
                        </label>
                        <input
                            v-model="nationalIdDisplay"
                            inputmode="numeric"
                            type="text"
                            class="w-full rounded-2xl border border-slate-200 px-5 py-4 text-lg tracking-widest outline-none transition focus:border-blue-500 focus:ring-4 focus:ring-blue-100"
                            placeholder="2 9 0 0 1 0 1 2 3 4 5 6 7 8"
                        />
                        <p v-if="fieldErrors.national_id" class="mt-2 text-sm text-red-600">{{ fieldErrors.national_id }}</p>
                    </div>

                    <div>
                        <label class="mb-2 flex items-center gap-2 text-sm font-semibold text-slate-700">
                            <FileText class="h-4 w-4" />
                            رقم الطلب
                        </label>
                        <input
                            v-model="form.order_number"
                            type="text"
                            class="w-full rounded-2xl border border-slate-200 px-5 py-4 text-lg outline-none transition focus:border-blue-500 focus:ring-4 focus:ring-blue-100"
                            placeholder="أدخل رقم الطلب"
                        />
                        <p v-if="fieldErrors.order_number" class="mt-2 text-sm text-red-600">{{ fieldErrors.order_number }}</p>
                    </div>

                    <button
                        type="submit"
                        :disabled="queueStore.loading || !queueStore.isSystemOpen"
                        class="flex w-full items-center justify-center gap-3 rounded-2xl bg-blue-600 px-6 py-5 text-xl font-bold text-white transition hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-60"
                    >
                        <Ticket class="h-6 w-6" />
                        {{ !queueStore.isSystemOpen ? 'النظام مغلق' : (queueStore.loading ? 'جاري الإصدار...' : 'إصدار التذكرة') }}
                    </button>
                    </fieldset>
                </form>
            </div>

            <div class="mt-6 rounded-3xl border border-slate-200 bg-white p-5 shadow-xl">
                <p class="mb-4 text-center text-sm text-slate-500">اعرف رقمك وحالة تذكرتك</p>
                <RouterLink
                    to="/track"
                    class="flex w-full items-center justify-center gap-2 rounded-2xl bg-blue-600 px-5 py-3 text-base font-bold text-white transition hover:bg-blue-700"
                >
                    <Search class="h-5 w-5" />
                    متابعة دورك
                </RouterLink>
            </div>

            <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-xl">
                    <p class="mb-4 text-center text-sm text-slate-500">قدّم طلب الالتحاق من هنا</p>
                    <a
                        href="https://batechu.com/admission"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="flex w-full items-center justify-center gap-2 rounded-2xl bg-emerald-600 px-5 py-3 text-base font-bold text-white transition hover:bg-emerald-700"
                    >
                        <FilePlus class="h-5 w-5" />
                        تقدم الطلب
                    </a>
                </div>
                <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-xl">
                    <p class="mb-4 text-center text-sm text-slate-500">اعرف حالة طلبك في أي وقت</p>
                    <a
                        href="https://batechu.com/admission/track"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="flex w-full items-center justify-center gap-2 rounded-2xl bg-indigo-600 px-5 py-3 text-base font-bold text-white transition hover:bg-indigo-700"
                    >
                        <ExternalLink class="h-5 w-5" />
                        تتبع طلبك
                    </a>
                </div>
            </div>
        </main>

        <div
            v-if="showModal && issuedTicket"
            class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-slate-900/60 p-4 backdrop-blur-sm sm:items-center sm:p-6"
            @click.self="closeModal"
        >
            <div class="my-6 w-full max-w-md animate-[fadeIn_0.3s_ease] rounded-3xl bg-white p-8 text-center shadow-2xl sm:my-0">
                <div id="ticket-print-area" ref="ticketCaptureRef" class="rounded-2xl bg-white p-2">
                    <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-green-100 text-green-600">
                        <Ticket class="h-8 w-8" />
                    </div>
                    <p class="text-sm text-slate-500">تم إصدار تذكرتك بنجاح</p>
                    <p class="my-3 text-6xl font-black text-blue-600">{{ issuedTicket.ticket_number }}</p>
                    <p class="text-lg font-semibold text-slate-800">{{ issuedTicket.masked_name }}</p>
                    <div class="mt-5 flex justify-center">
                        <TicketQrCode :value="ticketScanUrl(issuedTicket)" :size="176" />
                    </div>
                    <p class="mt-3 text-sm font-semibold text-slate-600">امسح الرمز لعرض بياناتك</p>
                    <p class="mt-1 text-sm text-slate-500">يرجى الانتظار حتى يتم نداؤك</p>
                    <p class="mt-4 text-xs text-slate-400">{{ new Date().toLocaleString('ar-EG') }}</p>
                    <div class="mt-5 flex flex-col gap-3 border-t border-slate-100 pt-4">
                        <a
                            :href="admissionApplyUrl"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="rounded-2xl bg-emerald-50 px-4 py-3"
                        >
                            <span class="block text-sm font-bold text-emerald-800">تقدم الطلب</span>
                            <span class="mt-1 block text-xs text-slate-600" dir="ltr">{{ admissionApplyUrl }}</span>
                        </a>
                        <a
                            :href="admissionTrackUrl"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="rounded-2xl bg-indigo-50 px-4 py-3"
                        >
                            <span class="block text-sm font-bold text-indigo-800">تتبع طلبك</span>
                            <span class="mt-1 block text-xs text-slate-600" dir="ltr">{{ admissionTrackUrl }}</span>
                        </a>
                    </div>
                </div>

                <p v-if="imageSaveError" class="mt-4 rounded-xl bg-red-50 px-4 py-3 text-sm text-red-700">
                    {{ imageSaveError }}
                </p>

                <div class="mt-8 grid grid-cols-2 gap-3">
                    <button
                        class="flex items-center justify-center gap-2 rounded-2xl bg-blue-600 px-4 py-3 font-semibold text-white hover:bg-blue-700"
                        @click="printTicket"
                    >
                        <Printer class="h-5 w-5" />
                        طباعة
                    </button>
                    <button
                        class="flex items-center justify-center gap-2 rounded-2xl bg-indigo-600 px-4 py-3 font-semibold text-white hover:bg-indigo-700 disabled:opacity-60"
                        :disabled="savingImage"
                        @click="saveAsImage"
                    >
                        <ImageDown class="h-5 w-5" />
                        {{ savingImage ? 'جاري الحفظ...' : 'حفظ صورة' }}
                    </button>
                    <button
                        class="col-span-2 rounded-2xl border border-slate-200 px-4 py-3 font-semibold text-slate-700 hover:bg-slate-50"
                        @click="closeModal"
                    >
                        إغلاق
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
