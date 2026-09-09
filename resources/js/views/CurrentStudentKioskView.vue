<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { RouterLink } from 'vue-router';
import {
    ArrowRight,
    Building2,
    FileText,
    Hash,
    ImageUp,
    Lock,
    Search,
    Ticket,
    User,
} from 'lucide-vue-next';
import { useQueueStore } from '../stores/queueStore';
import AppNavbar from '../components/AppNavbar.vue';
import IssuedTicketModal from '../components/IssuedTicketModal.vue';

const queueStore = useQueueStore();

const form = ref({
    full_name: '',
    college: '',
    department: '',
    seat_number: '',
    document_kind: '',
    document: null,
});

const documentName = ref('');
const showModal = ref(false);
const issuedTicket = ref(null);
const fieldErrors = ref({});
let stopAutoRefresh = null;
let unsubscribeEcho = null;

const faculties = computed(() => (
    (queueStore.system.faculties ?? []).length
        ? queueStore.system.faculties
        : [
            { value: 'industry_energy', label: 'صناعة وطاقة' },
            { value: 'health_sciences', label: 'علوم صحية' },
        ]
));
const documentKinds = computed(() => (
    (queueStore.system.document_kinds ?? []).length
        ? queueStore.system.document_kinds
        : [
            { value: 'status_statement', label: 'إيصال دفع بيان الحالة وحسن السير والسلوك' },
            { value: 'student_card', label: 'الكارنيه' },
        ]
));

function restrictDigitInput(event, maxLength) {
    const digits = event.target.value.replace(/\D/g, '').slice(0, maxLength);
    event.target.value = digits;

    return digits;
}

function onSeatNumberInput(event) {
    form.value.seat_number = restrictDigitInput(event, 7);
}

function onDocumentChange(event) {
    const file = event.target.files?.[0] ?? null;
    form.value.document = file;
    documentName.value = file?.name ?? '';
}

function emptyForm() {
    return {
        full_name: '',
        college: '',
        department: '',
        seat_number: '',
        document_kind: '',
        document: null,
    };
}

function validateClient() {
    const errors = {};

    if (!form.value.full_name || form.value.full_name.trim().length < 3) {
        errors.full_name = 'الاسم الكامل مطلوب (3 أحرف على الأقل).';
    }

    if (!form.value.college || !faculties.value.some((faculty) => faculty.value === form.value.college)) {
        errors.college = 'يجب اختيار الكلية.';
    }

    if (!form.value.department || form.value.department.trim().length < 2) {
        errors.department = 'يجب كتابة القسم.';
    }

    if (!/^\d{7}$/.test(form.value.seat_number)) {
        errors.seat_number = 'يجب أن يتكون رقم الجلوس من 7 أرقام.';
    }

    if (!form.value.document_kind) {
        errors.document_kind = 'يجب اختيار نوع المستند.';
    }

    if (!form.value.document) {
        errors.document = 'يجب رفع صورة المستند.';
    }

    fieldErrors.value = errors;
    return Object.keys(errors).length === 0;
}

async function submitForm() {
    if (!validateClient()) {
        return;
    }

    const payload = new FormData();
    payload.append('student_kind', 'current_student');
    payload.append('full_name', form.value.full_name.trim());
    payload.append('college', form.value.college);
    payload.append('department', form.value.department.trim());
    payload.append('seat_number', form.value.seat_number);
    payload.append('document_kind', form.value.document_kind);
    payload.append('document', form.value.document);

    try {
        const ticket = await queueStore.issueTicket(payload);

        issuedTicket.value = ticket;
        showModal.value = true;
        form.value = emptyForm();
        documentName.value = '';
        fieldErrors.value = {};
    } catch {
        fieldErrors.value.general = queueStore.error;
    }
}

function closeModal() {
    showModal.value = false;
    issuedTicket.value = null;
}

onMounted(() => {
    queueStore.fetchPublicStatus();
    unsubscribeEcho = queueStore.subscribeEcho();
    stopAutoRefresh = queueStore.startAutoRefresh(() => queueStore.fetchPublicStatus({ silent: true }), 5000);
});

onUnmounted(() => {
    unsubscribeEcho?.();
    stopAutoRefresh?.();
});
</script>

<template>
    <div class="min-h-screen bg-gradient-to-br from-blue-50 via-white to-indigo-50">
        <AppNavbar title="نظام إدارة الأدوار" subtitle="طالب حالي — فرقة ثانية" max-width="5xl">
            <div class="flex items-center gap-3 rounded-2xl bg-indigo-600 px-4 py-2 text-white shadow-lg sm:px-5 sm:py-3">
                <Ticket class="h-5 w-5 sm:h-6 sm:w-6" />
                <div class="text-left">
                    <p class="text-[10px] opacity-80 sm:text-xs">في الانتظار</p>
                    <p class="text-xl font-bold leading-none sm:text-2xl">{{ queueStore.stats.waiting }}</p>
                </div>
            </div>
        </AppNavbar>

        <main class="mx-auto max-w-3xl px-6 py-10">
            <RouterLink
                to="/"
                class="mb-6 inline-flex items-center gap-2 text-sm font-semibold text-slate-600 hover:text-indigo-700"
            >
                <ArrowRight class="h-4 w-4" />
                الرجوع لاختيار نوع الطالب
            </RouterLink>

            <div
                v-if="!queueStore.isSystemOpen"
                class="mb-6 rounded-3xl border border-red-200 bg-red-50 p-6 text-center"
            >
                <Lock class="mx-auto mb-3 h-10 w-10 text-red-500" />
                <h2 class="text-xl font-bold text-red-800">النظام مغلق حالياً</h2>
                <p class="mt-2 text-red-700">{{ queueStore.system.closed_message || 'لا يمكن إصدار تذاكر جديدة في الوقت الحالي.' }}</p>
            </div>
            <div
                v-else-if="!queueStore.isAcceptingTickets"
                class="mb-6 rounded-3xl border border-amber-200 bg-amber-50 p-6 text-center"
            >
                <Lock class="mx-auto mb-3 h-10 w-10 text-amber-500" />
                <h2 class="text-xl font-bold text-amber-800">انتهى استقبال الطلبات اليوم</h2>
                <p class="mt-2 text-amber-700">{{ queueStore.system.day_ended_message || 'لا يمكن تسجيل ناس جديدة الآن. يمكن متابعة الطلبات الحالية.' }}</p>
            </div>

            <div class="rounded-3xl border border-slate-200 bg-white p-8 shadow-xl" :class="{ 'opacity-60': !queueStore.isAcceptingTickets }">
                <h2 class="mb-8 text-center text-3xl font-bold text-slate-800">بيانات الطالب الحالي</h2>

                <p v-if="fieldErrors.general" class="mb-6 rounded-xl bg-red-50 px-4 py-3 text-center text-red-700">
                    {{ fieldErrors.general }}
                </p>

                <form class="space-y-6" @submit.prevent="submitForm">
                    <fieldset :disabled="!queueStore.isAcceptingTickets" class="space-y-6">
                        <div>
                            <label class="mb-2 flex items-center gap-2 text-sm font-semibold text-slate-700">
                                <User class="h-4 w-4" />
                                اسم الطالب
                            </label>
                            <input
                                v-model="form.full_name"
                                type="text"
                                class="w-full rounded-2xl border border-slate-200 px-5 py-4 text-lg outline-none transition focus:border-indigo-500 focus:ring-4 focus:ring-indigo-100"
                                placeholder="مثال: أحمد محمود علي"
                            />
                            <p v-if="fieldErrors.full_name" class="mt-2 text-sm text-red-600">{{ fieldErrors.full_name }}</p>
                        </div>

                        <div>
                            <label class="mb-2 flex items-center gap-2 text-sm font-semibold text-slate-700">
                                <Building2 class="h-4 w-4" />
                                الكلية
                            </label>
                            <div class="grid gap-3">
                                <label
                                    v-for="faculty in faculties"
                                    :key="faculty.value"
                                    class="flex cursor-pointer items-center gap-3 rounded-2xl border px-5 py-4 transition"
                                    :class="form.college === faculty.value
                                        ? 'border-indigo-500 bg-indigo-50 ring-4 ring-indigo-100'
                                        : 'border-slate-200 hover:border-indigo-300'"
                                >
                                    <input
                                        v-model="form.college"
                                        type="radio"
                                        :value="faculty.value"
                                        class="h-4 w-4 accent-indigo-600"
                                    />
                                    <span class="text-base font-semibold text-slate-800">{{ faculty.label }}</span>
                                </label>
                            </div>
                            <p v-if="fieldErrors.college" class="mt-2 text-sm text-red-600">{{ fieldErrors.college }}</p>
                        </div>

                        <div>
                            <label class="mb-2 flex items-center gap-2 text-sm font-semibold text-slate-700">
                                <FileText class="h-4 w-4" />
                                القسم
                            </label>
                            <input
                                v-model="form.department"
                                type="text"
                                class="w-full rounded-2xl border border-slate-200 px-5 py-4 text-lg outline-none transition focus:border-indigo-500 focus:ring-4 focus:ring-indigo-100"
                                placeholder="اكتب اسم القسم"
                            />
                            <p v-if="fieldErrors.department" class="mt-2 text-sm text-red-600">{{ fieldErrors.department }}</p>
                        </div>

                        <div>
                            <label class="mb-2 flex items-center gap-2 text-sm font-semibold text-slate-700">
                                <Hash class="h-4 w-4" />
                                رقم الجلوس (7 أرقام)
                            </label>
                            <input
                                :value="form.seat_number"
                                inputmode="numeric"
                                type="text"
                                maxlength="7"
                                pattern="[0-9]*"
                                autocomplete="off"
                                class="w-full rounded-2xl border border-slate-200 px-5 py-4 text-lg tracking-widest outline-none transition focus:border-indigo-500 focus:ring-4 focus:ring-indigo-100"
                                placeholder="أدخل رقم الجلوس"
                                @input="onSeatNumberInput"
                            />
                            <p v-if="fieldErrors.seat_number" class="mt-2 text-sm text-red-600">{{ fieldErrors.seat_number }}</p>
                        </div>

                        <div>
                            <label class="mb-2 flex items-center gap-2 text-sm font-semibold text-slate-700">
                                <ImageUp class="h-4 w-4" />
                                نوع المستند
                            </label>
                            <div class="grid gap-3">
                                <label
                                    v-for="kind in documentKinds"
                                    :key="kind.value"
                                    class="flex cursor-pointer items-center gap-3 rounded-2xl border px-5 py-4 transition"
                                    :class="form.document_kind === kind.value
                                        ? 'border-indigo-500 bg-indigo-50 ring-4 ring-indigo-100'
                                        : 'border-slate-200 hover:border-indigo-300'"
                                >
                                    <input
                                        v-model="form.document_kind"
                                        type="radio"
                                        :value="kind.value"
                                        class="h-4 w-4 accent-indigo-600"
                                    />
                                    <span class="text-base font-semibold text-slate-800">{{ kind.label }}</span>
                                </label>
                            </div>
                            <p v-if="fieldErrors.document_kind" class="mt-2 text-sm text-red-600">{{ fieldErrors.document_kind }}</p>
                        </div>

                        <div>
                            <label class="mb-2 flex items-center gap-2 text-sm font-semibold text-slate-700">
                                <ImageUp class="h-4 w-4" />
                                رفع صورة المستند
                            </label>
                            <input
                                type="file"
                                accept="image/jpeg,image/png,image/webp"
                                class="w-full rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-5 py-4 text-sm text-slate-700 file:me-4 file:rounded-xl file:border-0 file:bg-indigo-600 file:px-4 file:py-2 file:font-semibold file:text-white"
                                @change="onDocumentChange"
                            />
                            <p v-if="documentName" class="mt-2 text-sm text-slate-500">{{ documentName }}</p>
                            <p v-if="fieldErrors.document" class="mt-2 text-sm text-red-600">{{ fieldErrors.document }}</p>
                        </div>

                        <button
                            type="submit"
                            :disabled="queueStore.loading || !queueStore.isAcceptingTickets"
                            class="flex w-full items-center justify-center gap-3 rounded-2xl bg-indigo-600 px-6 py-5 text-xl font-bold text-white transition hover:bg-indigo-700 disabled:cursor-not-allowed disabled:opacity-60"
                        >
                            <Ticket class="h-6 w-6" />
                            {{ !queueStore.isAcceptingTickets
                                ? (queueStore.isSystemOpen ? 'انتهى استقبال الطلبات' : 'النظام مغلق')
                                : (queueStore.loading ? 'جاري الإصدار...' : 'إصدار التذكرة') }}
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
        </main>

        <IssuedTicketModal
            v-if="showModal && issuedTicket"
            :ticket="issuedTicket"
            @close="closeModal"
        />
    </div>
</template>
