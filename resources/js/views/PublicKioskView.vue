<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { RouterLink } from 'vue-router';
import html2canvas from 'html2canvas';
import { Ticket, Printer, User, Hash, FileText, Lock, Search, ImageDown } from 'lucide-vue-next';
import { useQueueStore } from '../stores/queueStore';

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
const ticketCaptureRef = ref(null);

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
}

function printTicket() {
    window.print();
}

async function saveAsImage() {
    if (!ticketCaptureRef.value || !issuedTicket.value) {
        return;
    }

    savingImage.value = true;

    try {
        const canvas = await html2canvas(ticketCaptureRef.value, {
            backgroundColor: '#ffffff',
            scale: 2,
            useCORS: true,
        });

        const link = document.createElement('a');
        link.download = `ticket-${issuedTicket.value.ticket_number}.png`;
        link.href = canvas.toDataURL('image/png');
        link.click();
    } catch {
        fieldErrors.value.general = 'تعذر حفظ الصورة. حاول مرة أخرى.';
    } finally {
        savingImage.value = false;
    }
}

onMounted(async () => {
    queueStore.bindEcho();
    await queueStore.fetchPublicStatus();
});

onUnmounted(() => {
    queueStore.unbindEcho();
});
</script>

<template>
    <div class="min-h-screen bg-gradient-to-br from-blue-50 via-white to-indigo-50">
        <header class="border-b border-blue-100 bg-white/80 backdrop-blur">
            <div class="mx-auto flex max-w-5xl items-center justify-between px-6 py-5">
                <div>
                    <h1 class="text-2xl font-bold text-slate-900">نظام إدارة الطوابير</h1>
                    <p class="text-sm text-slate-500">إصدار تذكرة جديدة</p>
                </div>
                <div class="flex items-center gap-3">
                    <RouterLink
                        to="/track"
                        class="flex items-center gap-2 rounded-2xl border border-blue-200 bg-white px-4 py-3 text-sm font-semibold text-blue-700 shadow-sm hover:bg-blue-50"
                    >
                        <Search class="h-5 w-5" />
                        متابعة التذكرة
                    </RouterLink>
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
        </main>

        <div
            v-if="showModal && issuedTicket"
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 p-6 backdrop-blur-sm"
            @click.self="closeModal"
        >
            <div class="w-full max-w-md animate-[fadeIn_0.3s_ease] rounded-3xl bg-white p-8 text-center shadow-2xl">
                <div id="ticket-print-area" ref="ticketCaptureRef" class="rounded-2xl bg-white p-2">
                    <div class="mx-auto mb-6 flex h-20 w-20 items-center justify-center rounded-full bg-green-100 text-green-600">
                        <Ticket class="h-10 w-10" />
                    </div>
                    <p class="text-sm text-slate-500">تم إصدار تذكرتك بنجاح</p>
                    <p class="my-4 text-6xl font-black text-blue-600">{{ issuedTicket.ticket_number }}</p>
                    <p class="text-lg font-semibold text-slate-800">{{ issuedTicket.masked_name }}</p>
                    <p class="mt-2 text-sm text-slate-500">يرجى الانتظار حتى يتم نداؤك</p>
                    <p class="mt-4 text-xs text-slate-400">{{ new Date().toLocaleString('ar-EG') }}</p>
                </div>

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
