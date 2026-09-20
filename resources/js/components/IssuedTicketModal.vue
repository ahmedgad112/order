<script setup>
import { computed, nextTick, onMounted, ref } from 'vue';
import { ImageDown, Printer, Ticket } from 'lucide-vue-next';

const props = defineProps({
    ticket: {
        type: Object,
        required: true,
    },
    tickets: {
        type: Array,
        default: () => [],
    },
    allowPrint: {
        type: Boolean,
        default: false,
    },
    allowDownload: {
        type: Boolean,
        default: true,
    },
    showAdmissionLinks: {
        type: Boolean,
        default: true,
    },
    autoPrint: {
        type: Boolean,
        default: false,
    },
    heading: {
        type: String,
        default: 'تم إصدار تذكرتك بنجاح',
    },
});

const emit = defineEmits(['close']);

const printableTickets = computed(() => (
    props.tickets.length > 0 ? props.tickets : [props.ticket]
));
const previewTicket = computed(() => printableTickets.value[0] ?? props.ticket);
const ticketCodesLabel = computed(() => printableTickets.value
    .map((item) => item.ticket_number)
    .join(' · '));
const modalHeading = computed(() => {
    if (printableTickets.value.length > 1) {
        return `تم إصدار ${printableTickets.value.length} أدوار`;
    }

    return props.heading;
});
const hasBothActions = computed(() => props.allowPrint && props.allowDownload);

const savingImage = ref(false);
const imageSaveError = ref('');
const ticketCaptureRef = ref(null);

const ticketFontFamily = '"Segoe UI", Tahoma, Arial, sans-serif';
const universityName = 'جامعة برج العرب التكنولوجية';
const universityWelcome = 'ترحب بكم';
const admissionApplyUrl = 'https://batechu.com/admission';
const admissionTrackUrl = 'https://batechu.com/admission/track';

function ticketDisplayName(ticket) {
    if (ticket?.masked_name) {
        return ticket.masked_name;
    }

    if (ticket?.full_name) {
        return ticket.full_name;
    }

    if (ticket?.order_number) {
        return `رقم الطلب ${ticket.order_number}`;
    }

    return '';
}

async function renderTicketToCanvas(ticket) {
    const width = 280;
    const height = 260;
    const scale = 2;
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

    ctx.fillStyle = '#000000';
    ctx.font = `bold 14px ${ticketFontFamily}`;
    ctx.fillText(universityName, width / 2, 28);
    ctx.font = `bold 13px ${ticketFontFamily}`;
    ctx.fillText(universityWelcome, width / 2, 50);

    ctx.font = `12px ${ticketFontFamily}`;
    ctx.fillText('تم إصدار تذكرتك بنجاح', width / 2, 78);

    ctx.direction = 'ltr';
    ctx.font = `bold 48px ${ticketFontFamily}`;
    ctx.fillText(String(ticket.ticket_number), width / 2, 130);
    ctx.direction = 'rtl';

    const name = ticketDisplayName(ticket);
    if (name) {
        ctx.font = `bold 14px ${ticketFontFamily}`;
        ctx.fillText(name, width / 2, 172);
    }

    ctx.font = `12px ${ticketFontFamily}`;
    ctx.fillText('يرجى الانتظار حتى يتم نداؤك', width / 2, 204);

    ctx.font = `11px ${ticketFontFamily}`;
    ctx.fillText(new Date().toLocaleString('ar-EG'), width / 2, 232);

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
    const source = ticketCaptureRef.value;
    if (!source) {
        return;
    }

    document.querySelectorAll('.ticket-print-root').forEach((node) => node.remove());

    const printRoot = document.createElement('div');
    printRoot.className = 'ticket-print-root';
    printRoot.appendChild(source.cloneNode(true));
    document.body.appendChild(printRoot);

    const cleanup = () => {
        printRoot.remove();
        window.removeEventListener('afterprint', cleanup);
    };

    window.addEventListener('afterprint', cleanup);
    window.print();
}

async function saveAsImage() {
    savingImage.value = true;
    imageSaveError.value = '';

    try {
        const canvas = await renderTicketToCanvas(previewTicket.value);
        const filename = `ticket-${previewTicket.value.ticket_number}.png`;

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

onMounted(async () => {
    if (!props.autoPrint || !props.allowPrint) {
        return;
    }

    await nextTick();
    printTicket();
});
</script>

<template>
    <Teleport to="body">
    <div
        class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-slate-900/60 p-4 backdrop-blur-sm sm:items-center sm:p-6"
        @click.self="close"
    >
        <div class="my-6 w-full max-w-xs animate-[fadeIn_0.3s_ease] rounded-2xl bg-white p-5 text-center shadow-2xl sm:my-0">
            <p
                v-if="printableTickets.length > 1"
                class="ticket-print-hide mb-3 rounded-xl bg-slate-50 px-3 py-2 text-sm font-semibold text-slate-700"
            >
                {{ ticketCodesLabel }}
            </p>
            <div id="ticket-print-area" ref="ticketCaptureRef">
                <article
                    v-for="(item, index) in printableTickets"
                    :key="item.id"
                    class="ticket-print-area ticket-print-page rounded-xl bg-white p-1"
                    :class="{ hidden: index > 0 }"
                >
                    <p class="ticket-welcome text-sm font-extrabold leading-snug text-slate-900">
                        <span class="block">{{ universityName }}</span>
                        <span class="block">{{ universityWelcome }}</span>
                    </p>
                    <div class="ticket-print-hide mx-auto mb-2 flex h-10 w-10 items-center justify-center rounded-full bg-slate-100 text-slate-700">
                        <Ticket class="h-5 w-5" />
                    </div>
                    <p class="text-xs text-slate-900">{{ index === 0 ? modalHeading : heading }}</p>
                    <p class="ticket-number my-2 text-4xl font-black text-slate-900" dir="ltr">{{ item.ticket_number }}</p>
                    <p v-if="ticketDisplayName(item)" class="text-sm font-semibold text-slate-900">{{ ticketDisplayName(item) }}</p>
                    <p class="mt-2 text-xs text-slate-900">يرجى الانتظار حتى يتم نداؤك</p>
                    <p class="mt-2 text-[11px] text-slate-900">{{ new Date().toLocaleString('ar-EG') }}</p>
                    <div v-if="showAdmissionLinks" class="ticket-print-hide mt-4 flex flex-col gap-2 border-t border-slate-100 pt-3">
                        <a
                            :href="admissionApplyUrl"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="rounded-xl bg-slate-100 px-3 py-2"
                        >
                            <span class="block text-sm font-bold text-slate-900">تقدم الطلب</span>
                            <span class="mt-1 block text-xs text-slate-900" dir="ltr">{{ admissionApplyUrl }}</span>
                        </a>
                        <a
                            :href="admissionTrackUrl"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="rounded-xl bg-slate-100 px-3 py-2"
                        >
                            <span class="block text-sm font-bold text-slate-900">تتبع طلبك</span>
                            <span class="mt-1 block text-xs text-slate-900" dir="ltr">{{ admissionTrackUrl }}</span>
                        </a>
                    </div>
                </article>
            </div>

            <p v-if="imageSaveError" class="mt-3 rounded-xl bg-red-50 px-3 py-2 text-sm text-red-700">
                {{ imageSaveError }}
            </p>

            <p v-if="allowDownload && !allowPrint" class="mt-3 text-sm font-semibold text-slate-500">
                احفظ الصورة على هاتفك. الطباعة تتم عند موظف الشباك.
            </p>

            <div class="mt-5 grid gap-2" :class="hasBothActions ? 'grid-cols-2' : 'grid-cols-1'">
                <button
                    v-if="allowPrint"
                    type="button"
                    class="flex items-center justify-center gap-2 rounded-xl bg-blue-600 px-3 py-2.5 text-sm font-semibold text-white hover:bg-blue-700"
                    @click="printTicket"
                >
                    <Printer class="h-4 w-4" />
                    {{ printableTickets.length > 1 ? 'طباعة الكل' : 'طباعة' }}
                </button>
                <button
                    v-if="allowDownload"
                    type="button"
                    class="flex items-center justify-center gap-2 rounded-xl bg-indigo-600 px-3 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700 disabled:opacity-60"
                    :disabled="savingImage"
                    @click="saveAsImage"
                >
                    <ImageDown class="h-4 w-4" />
                    {{ savingImage ? 'جاري الحفظ...' : 'حفظ صورة' }}
                </button>
                <button
                    type="button"
                    class="rounded-xl border border-slate-200 px-3 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                    :class="{ 'col-span-2': hasBothActions }"
                    @click="close"
                >
                    إغلاق
                </button>
            </div>
        </div>
    </div>
    </Teleport>
</template>
