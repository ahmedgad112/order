<script setup>
import { defineAsyncComponent, ref } from 'vue';
import { ImageDown, Printer, Ticket } from 'lucide-vue-next';

const TicketQrCode = defineAsyncComponent(() => import('./TicketQrCode.vue'));

const props = defineProps({
    ticket: {
        type: Object,
        required: true,
    },
});

const emit = defineEmits(['close']);

const savingImage = ref(false);
const imageSaveError = ref('');
const ticketCaptureRef = ref(null);

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
    ctx.direction = 'ltr';
    ctx.font = `bold 68px ${ticketFontFamily}`;
    ctx.fillText(String(ticket.ticket_number), width / 2, 175);
    ctx.direction = 'rtl';

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

function printTicket() {
    window.print();
}

async function saveAsImage() {
    savingImage.value = true;
    imageSaveError.value = '';

    try {
        const canvas = await renderTicketToCanvas(props.ticket);
        const filename = `ticket-${props.ticket.ticket_number}.png`;

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

function close() {
    imageSaveError.value = '';
    emit('close');
}
</script>

<template>
    <div
        class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-slate-900/60 p-4 backdrop-blur-sm sm:items-center sm:p-6"
        @click.self="close"
    >
        <div class="my-6 w-full max-w-md animate-[fadeIn_0.3s_ease] rounded-3xl bg-white p-8 text-center shadow-2xl sm:my-0">
            <div id="ticket-print-area" ref="ticketCaptureRef" class="rounded-2xl bg-white p-2">
                <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-green-100 text-green-600">
                    <Ticket class="h-8 w-8" />
                </div>
                <p class="text-sm text-slate-500">تم إصدار تذكرتك بنجاح</p>
                <p class="my-3 text-6xl font-black text-blue-600" dir="ltr">{{ ticket.ticket_number }}</p>
                <p class="text-lg font-semibold text-slate-800">{{ ticket.masked_name }}</p>
                <div class="mt-5 flex justify-center">
                    <TicketQrCode :value="ticketScanUrl(ticket)" :size="176" />
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
                    @click="close"
                >
                    إغلاق
                </button>
            </div>
        </div>
    </div>
</template>
