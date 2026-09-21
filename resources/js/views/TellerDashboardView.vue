<script setup>
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import {
    BellRing,
    ChevronDown,
    ClipboardList,
    Hash,
    Lock,
    PhoneCall,
    Plus,
    RefreshCw,
    RotateCcw,
    SkipForward,
    UserX,
    VolumeX,
} from 'lucide-vue-next';
import { useAuthStore } from '../stores/authStore';
import { useQueueStore } from '../stores/queueStore';
import AppNavbar from '../components/AppNavbar.vue';
import TicketProcessActions from '../components/TicketProcessActions.vue';
import TicketDeleteButton from '../components/TicketDeleteButton.vue';
import TicketEditForm from '../components/TicketEditForm.vue';
import TicketDocumentLink from '../components/TicketDocumentLink.vue';
import TicketPrintButton from '../components/TicketPrintButton.vue';
import StaffIssueTicketModal from '../components/StaffIssueTicketModal.vue';
import IssuedTicketModal from '../components/IssuedTicketModal.vue';

const authStore = useAuthStore();
const queueStore = useQueueStore();
const actionMessage = ref('');
const actionError = ref('');
const restoringId = ref(null);
const processBusyId = ref(null);
const busyStep = ref(null);
const absentId = ref(null);
const deletingId = ref(null);
const editingId = ref(null);
const callingId = ref(null);
const restartingCalls = ref(false);
const skippingId = ref(null);
const recallingId = ref(null);
const search = ref('');
const searchBy = ref('all');
const stepFilter = ref('all');
const typeFilter = ref('all');
const loading = ref(false);
const showIssueForm = ref(false);
const issueRequiresName = ref(true);
const issuedTicket = ref(null);
const issuedTickets = ref([]);
const servingNowCollapsedKey = 'teller.servingNowCollapsed';
const servingNowCollapsed = ref(readServingNowCollapsed());

function readServingNowCollapsed() {
    try {
        return localStorage.getItem(servingNowCollapsedKey) !== '0';
    } catch {
        return true;
    }
}

function toggleServingNow() {
    servingNowCollapsed.value = !servingNowCollapsed.value;

    try {
        localStorage.setItem(servingNowCollapsedKey, servingNowCollapsed.value ? '1' : '0');
    } catch {
        // Ignore storage errors from private browsing.
    }
}

const processStepValues = ['entered', 'paid', 'file_withdrawn', 'documents_reviewed', 'medical_checked', 'face_printed', 'file_delivered'];

const statusLabel = {
    waiting: 'في الانتظار',
    serving: 'قيد الخدمة',
    completed: 'مكتمل',
    cancelled: 'ملغى',
    absent: 'مش موجود',
};

const statusBadgeClass = {
    waiting: 'bg-amber-100 text-amber-800',
    serving: 'bg-blue-100 text-blue-800',
    completed: 'bg-green-100 text-green-800',
    cancelled: 'bg-red-100 text-red-800',
    absent: 'bg-orange-100 text-orange-800',
};

const stepOptions = [
    { value: 'all', label: 'الكل' },
    { value: 'entered', label: 'طلب دخول' },
    { value: 'paid', label: 'دفع' },
    { value: 'file_withdrawn', label: 'سحب ملف' },
    { value: 'documents_reviewed', label: 'مراجعة ورق' },
    { value: 'medical_checked', label: 'كشف طبي' },
    { value: 'face_printed', label: 'بصمة وجه' },
    { value: 'file_delivered', label: 'تسليم الملف' },
    { value: 'completed', label: 'مكتمل' },
    { value: 'absent', label: 'مش موجود' },
    { value: 'cancelled', label: 'ملغى' },
];
const searchFieldOptions = [
    { value: 'all', label: 'كل الحقول' },
    { value: 'full_name', label: 'الاسم' },
    { value: 'national_id', label: 'الرقم القومي' },
    { value: 'order_number', label: 'رقم الطلب' },
    { value: 'ticket_number', label: 'رقم التذكرة' },
    { value: 'seat_number', label: 'رقم الجلوس' },
    { value: 'department', label: 'القسم' },
    { value: 'college', label: 'الكلية' },
];
const searchPlaceholders = {
    all: 'بحث بالاسم، الرقم القومي، رقم الطلب، أو رقم التذكرة...',
    full_name: 'بحث بالاسم...',
    national_id: 'بحث بالرقم القومي...',
    order_number: 'بحث برقم الطلب...',
    ticket_number: 'بحث برقم التذكرة مثل OT1...',
    seat_number: 'بحث برقم الجلوس...',
    department: 'بحث بالقسم...',
    college: 'بحث بالكلية...',
};
const searchPlaceholder = computed(() => searchPlaceholders[searchBy.value] ?? searchPlaceholders.all);

const filteredCount = computed(() => queueStore.tellerTickets.length);
const assignedLanes = computed(() => authStore.user?.queue_lanes ?? []);

const typeOptions = computed(() => {
    const assigned = assignedLanes.value
        .map((lane) => ({
            value: lane.value ?? lane,
            label: lane.label ?? lane,
        }))
        .filter((lane) => lane.value);

    const types = assigned.length
        ? assigned
        : [
            ...(queueStore.system.request_types ?? []).map((type) => ({
                value: type.value,
                label: type.label,
            })),
            {
                value: 'current_student',
                label: (queueStore.system.student_kinds ?? [])
                    .find((kind) => kind.value === 'current_student')?.label
                    ?? 'طالب حالي (فرقة ثانية)',
            },
        ];

    return [
        { value: 'all', label: 'كل الأنواع' },
        ...types,
    ];
});

function listParams() {
    const params = {
        search: search.value.trim() || undefined,
        search_by: search.value.trim() && searchBy.value !== 'all' ? searchBy.value : undefined,
        request_type: typeFilter.value !== 'all' ? typeFilter.value : undefined,
    };

    if (processStepValues.includes(stepFilter.value)) {
        params.step = stepFilter.value;
    } else if (stepFilter.value !== 'all') {
        params.status = stepFilter.value;
    }

    return params;
}

const servingNow = computed(() => {
    const serving = (queueStore.serving ?? []).filter((ticket) => ticket.status === 'serving');
    const detailed = queueStore.tellerTickets.filter((ticket) => ticket.status === 'serving');
    const byId = Object.fromEntries(detailed.map((ticket) => [ticket.id, ticket]));

    if (!serving.length) {
        return detailed;
    }

    return serving.map((ticket) => byId[ticket.id] ?? ticket);
});

const navbarSubtitle = computed(() => {
    const parts = [authStore.user?.role_label, authStore.user?.name, authStore.user?.counter_name]
        .filter(Boolean);

    return parts.join(' — ');
});

async function loadTickets(silent = false) {
    if (!silent) {
        loading.value = true;
    }
    try {
        await queueStore.fetchTellerTickets(listParams());
    } finally {
        if (!silent) {
            loading.value = false;
        }
    }
}

async function refresh() {
    await loadTickets();
}

function canMarkAbsent(ticket) {
    return ['waiting', 'serving'].includes(ticket.status);
}

function openIssueForm(requireName) {
    issueRequiresName.value = requireName;
    showIssueForm.value = true;
}

function ticketPersonLabel(ticket) {
    return ticket.full_name || ticket.order_number || ticket.ticket_number;
}

function handleIssued(result) {
    showIssueForm.value = false;
    issuedTickets.value = result.tickets?.length ? result.tickets : (result.ticket ? [result.ticket] : []);
    issuedTicket.value = issuedTickets.value[0] ?? null;
    actionMessage.value = result.message ?? 'تم إصدار الدور بنجاح.';
    actionError.value = '';
}

function closeIssuedModal() {
    issuedTicket.value = null;
    issuedTickets.value = [];
}

async function handleProcessMark(ticket, step) {
    processBusyId.value = ticket.id;
    busyStep.value = step;
    actionError.value = '';
    try {
        const result = await queueStore.markProcessStep(ticket.id, step);
        actionMessage.value = result.message;
    } catch (err) {
        actionError.value = err.response?.data?.message
            ?? err.response?.data?.errors?.ticket?.[0]
            ?? 'تعذر تحديث الطلب.';
    } finally {
        processBusyId.value = null;
        busyStep.value = null;
    }
}

async function handleCallNext() {
    actionError.value = '';
    try {
        await queueStore.callNext();
        actionMessage.value = 'تم نداء التذكرة التالية.';
    } catch (err) {
        actionError.value = err.response?.data?.message
            ?? err.response?.data?.errors?.queue?.[0]
            ?? 'تعذر نداء التذكرة التالية.';
    }
}

async function handleRestartCalls() {
    if (!confirm('إلغاء كل النداءات وإرجاع كل التذاكر قيد الخدمة لقائمة الانتظار؟')) {
        return;
    }

    restartingCalls.value = true;
    actionError.value = '';
    try {
        const result = await queueStore.restartCalling();
        actionMessage.value = result.message ?? 'تم إلغاء النداءات المسجلة.';
    } catch (err) {
        actionError.value = err.response?.data?.message
            ?? 'تعذر إلغاء النداءات المسجلة.';
    } finally {
        restartingCalls.value = false;
    }
}

async function handleCallTicket(ticket) {
    callingId.value = ticket.id;
    actionError.value = '';
    try {
        await queueStore.callTicket(ticket.id);
        actionMessage.value = `تم نداء التذكرة ${ticket.ticket_number}.`;
    } catch (err) {
        actionError.value = err.response?.data?.message
            ?? err.response?.data?.errors?.ticket?.[0]
            ?? 'تعذر نداء التذكرة.';
    } finally {
        callingId.value = null;
    }
}

async function handleSkipTicket(ticket) {
    skippingId.value = ticket.id;
    actionError.value = '';
    try {
        const result = await queueStore.skipTicket(ticket.id);
        actionMessage.value = result.message ?? `تم تخطي التذكرة ${ticket.ticket_number}.`;
    } catch (err) {
        actionError.value = err.response?.data?.message
            ?? err.response?.data?.errors?.ticket?.[0]
            ?? 'تعذر تخطي التذكرة.';
    } finally {
        skippingId.value = null;
    }
}

async function handleRecall(ticket) {
    recallingId.value = ticket.id;
    actionError.value = '';
    try {
        await queueStore.recallTicket(ticket.id);
        actionMessage.value = `تم إعادة نداء التذكرة ${ticket.ticket_number}.`;
    } catch (err) {
        actionError.value = err.response?.data?.message
            ?? err.response?.data?.errors?.ticket?.[0]
            ?? 'تعذر إعادة نداء التذكرة.';
    } finally {
        recallingId.value = null;
    }
}

function canCall(ticket) {
    if (!queueStore.isSystemOpen) {
        return false;
    }

    if (ticket.status === 'waiting') {
        return true;
    }

    return ticket.status === 'serving' && ticket.user_id !== authStore.user?.id;
}

function canRecall(ticket) {
    return ticket.status === 'serving' && ticket.user_id === authStore.user?.id;
}

async function handleComplete() {
    if (!queueStore.currentTicket) {
        return;
    }
    actionError.value = '';
    try {
        await queueStore.completeTicket(queueStore.currentTicket.id);
        actionMessage.value = 'تم إكمال التذكرة.';
    } catch (err) {
        actionError.value = err.response?.data?.message ?? 'تعذر إكمال التذكرة.';
    }
}

async function handleCancel() {
    if (!queueStore.currentTicket) {
        return;
    }
    actionError.value = '';
    try {
        await queueStore.cancelTicket(queueStore.currentTicket.id);
        actionMessage.value = 'تم إلغاء التذكرة.';
    } catch (err) {
        actionError.value = err.response?.data?.message ?? 'تعذر إلغاء التذكرة.';
    }
}

async function handleMarkAbsent(ticket = queueStore.currentTicket) {
    if (!ticket) {
        return;
    }

    if (!confirm(`تسجيل التذكرة ${ticket.ticket_number} كـ "مش موجود"؟`)) {
        return;
    }

    absentId.value = ticket.id;
    actionError.value = '';
    try {
        const result = await queueStore.markAbsent(ticket.id);
        actionMessage.value = result.message;
    } catch (err) {
        actionError.value = err.response?.data?.message
            ?? err.response?.data?.errors?.ticket?.[0]
            ?? 'تعذر تسجيل "مش موجود".';
    } finally {
        absentId.value = null;
    }
}

async function handleRestore(ticket) {
    restoringId.value = ticket.id;
    actionError.value = '';
    try {
        const result = await queueStore.restoreTicket(ticket.id);
        actionMessage.value = result.message;
    } catch (err) {
        actionError.value = err.response?.data?.message
            ?? err.response?.data?.errors?.ticket?.[0]
            ?? 'تعذر إرجاع التذكرة.';
    } finally {
        restoringId.value = null;
    }
}

async function handleDelete(ticket) {
    deletingId.value = ticket.id;
    actionError.value = '';
    try {
        const result = await queueStore.deleteTicket(ticket.id);
        actionMessage.value = result.message;
        await loadTickets();
    } catch (err) {
        actionError.value = err.response?.data?.message
            ?? 'تعذر حذف الطلب.';
    } finally {
        deletingId.value = null;
    }
}

async function handleEdit(ticket) {
    editingId.value = ticket.id;
    actionError.value = '';
    try {
        const result = await queueStore.updateTicket(ticket.id, {
            full_name: ticket.full_name,
            national_id: ticket.national_id,
            request_type: ticket.request_type,
            completion_step: ticket.completion_step ?? null,
            college: ticket.college,
            order_number: ticket.order_number,
            department: ticket.department,
            seat_number: ticket.seat_number,
        });
        actionMessage.value = result.message;
        await loadTickets();
    } catch (err) {
        actionError.value = err.response?.data?.message
            ?? err.response?.data?.errors?.national_id?.[0]
            ?? err.response?.data?.errors?.full_name?.[0]
            ?? err.response?.data?.errors?.college?.[0]
            ?? 'تعذر تعديل الطلب.';
    } finally {
        editingId.value = null;
    }
}

function tellerLabel(ticket) {
    if (ticket.teller_name && ticket.counter_name && ticket.teller_name !== ticket.counter_name) {
        return `${ticket.teller_name} — ${ticket.counter_name}`;
    }

    return ticket.teller_name || ticket.counter_name || '—';
}

function formatTime(iso) {
    if (!iso) {
        return '—';
    }
    return new Date(iso).toLocaleTimeString('ar-EG', { hour: '2-digit', minute: '2-digit' });
}

function onKeydown(event) {
    if (event.target.tagName === 'INPUT' || event.target.tagName === 'TEXTAREA') {
        return;
    }

    if (event.code === 'Space') {
        event.preventDefault();
        handleCallNext();
    } else if (event.code === 'Enter') {
        event.preventDefault();
        handleComplete();
    } else if (event.code === 'Escape') {
        event.preventDefault();
        handleCancel();
    }
}

let searchTimer = null;
let unsubscribeEcho = null;
let stopAutoRefresh = null;

watch([stepFilter, typeFilter], () => loadTickets());

watch(search, () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => loadTickets(), 400);
});

watch(searchBy, () => {
    if (search.value.trim()) {
        loadTickets();
    }
});

onMounted(() => {
    refresh();
    window.addEventListener('keydown', onKeydown);

    unsubscribeEcho = queueStore.subscribeEcho({
        TicketIssued: () => loadTickets(true),
        TicketCalled: () => loadTickets(true),
        TicketCompleted: () => loadTickets(true),
        TicketAbsent: () => loadTickets(true),
        TicketRestored: () => loadTickets(true),
        TicketDeleted: () => loadTickets(true),
        TicketUpdated: () => loadTickets(true),
        QueueDayReset: () => loadTickets(true),
    });

    stopAutoRefresh = queueStore.startAutoRefresh(async () => {
        await loadTickets(true);
    }, 5000);
});

onUnmounted(() => {
    window.removeEventListener('keydown', onKeydown);
    clearTimeout(searchTimer);
    unsubscribeEcho?.();
    stopAutoRefresh?.();
});
</script>

<template>
    <AppNavbar title="لوحة الموظف" :subtitle="navbarSubtitle">
        <main class="mx-auto w-full max-w-7xl space-y-6 px-3 py-4 sm:px-6 sm:py-6">
            <div
                v-if="!queueStore.isSystemOpen"
                class="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-red-800"
            >
                <div class="flex items-center gap-2 font-semibold">
                    <Lock class="h-4 w-4" />
                    النظام مغلق — يمكن تسليم الملفات للتذاكر اللي دخلت بالفعل فقط.
                </div>
            </div>
            <div
                v-else-if="!queueStore.isDayOpen"
                class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-amber-900"
            >
                <div class="flex items-center gap-2 font-semibold">
                    <Lock class="h-4 w-4" />
                    {{ queueStore.system.day_ended_message || 'انتهى استقبال الطلبات اليوم — النظام شغال لمتابعة الطلبات الحالية.' }}
                </div>
            </div>

            <section class="flex flex-col gap-3 sm:flex-row sm:items-center">
                <button
                    type="button"
                    class="flex items-center justify-center gap-2 rounded-2xl bg-indigo-600 px-5 py-3 font-bold text-white hover:bg-indigo-700 disabled:opacity-50"
                    :disabled="!queueStore.isAcceptingTickets"
                    @click="openIssueForm(true)"
                >
                    <Plus class="h-5 w-5" />
                    دور جديد
                </button>
                <button
                    type="button"
                    class="flex items-center justify-center gap-2 rounded-2xl bg-slate-800 px-5 py-3 font-bold text-white hover:bg-slate-900 disabled:opacity-50"
                    :disabled="!queueStore.isAcceptingTickets"
                    @click="openIssueForm(false)"
                >
                    <Hash class="h-5 w-5" />
                    دور بالنوع
                </button>
            </section>

            <section
                v-if="authStore.isTeller"
                class="rounded-2xl border border-indigo-100 bg-white px-4 py-3 shadow-sm"
            >
                <p class="text-xs font-semibold text-slate-500">أنواع الطلب المخصصة لك</p>
                <div v-if="assignedLanes.length" class="mt-2 flex flex-wrap gap-2">
                    <span
                        v-for="lane in assignedLanes"
                        :key="lane.value"
                        class="rounded-full bg-indigo-50 px-3 py-1 text-xs font-semibold text-indigo-700"
                    >
                        {{ lane.label }}
                    </span>
                </div>
                <p v-else class="mt-2 text-sm font-semibold text-amber-700">
                    لم يتم تخصيص أي نوع طلب لحسابك. تواصل مع الإدارة.
                </p>
            </section>

            <section class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5">
                <div class="rounded-2xl bg-white p-4 shadow-sm">
                    <p class="text-xs text-slate-500">الإجمالي</p>
                    <p class="text-2xl font-black text-slate-800">{{ queueStore.tellerTicketStats.total }}</p>
                </div>
                <div class="rounded-2xl bg-amber-50 p-4 shadow-sm">
                    <p class="text-xs text-amber-700">انتظار</p>
                    <p class="text-2xl font-black text-amber-600">{{ queueStore.tellerTicketStats.waiting }}</p>
                </div>
                <div class="rounded-2xl bg-blue-50 p-4 shadow-sm">
                    <p class="text-xs text-blue-700">طلب دخول</p>
                    <p class="text-2xl font-black text-blue-600">{{ queueStore.tellerTicketStats.entered }}</p>
                </div>
                <div class="rounded-2xl bg-cyan-50 p-4 shadow-sm">
                    <p class="text-xs text-cyan-700">دفع</p>
                    <p class="text-2xl font-black text-cyan-600">{{ queueStore.tellerTicketStats.paid }}</p>
                </div>
                <div class="rounded-2xl bg-orange-50 p-4 shadow-sm">
                    <p class="text-xs text-orange-700">سحب ملف</p>
                    <p class="text-2xl font-black text-orange-600">{{ queueStore.tellerTicketStats.file_withdrawn }}</p>
                </div>
                <div class="rounded-2xl bg-indigo-50 p-4 shadow-sm">
                    <p class="text-xs text-indigo-700">مراجعة ورق</p>
                    <p class="text-2xl font-black text-indigo-600">{{ queueStore.tellerTicketStats.documents_reviewed }}</p>
                </div>
                <div class="rounded-2xl bg-teal-50 p-4 shadow-sm">
                    <p class="text-xs text-teal-700">كشف طبي</p>
                    <p class="text-2xl font-black text-teal-600">{{ queueStore.tellerTicketStats.medical_checked }}</p>
                </div>
                <div class="rounded-2xl bg-violet-50 p-4 shadow-sm">
                    <p class="text-xs text-violet-700">بصمة وجه</p>
                    <p class="text-2xl font-black text-violet-600">{{ queueStore.tellerTicketStats.face_printed }}</p>
                </div>
                <div class="rounded-2xl bg-green-50 p-4 shadow-sm">
                    <p class="text-xs text-green-700">تسليم الملف</p>
                    <p class="text-2xl font-black text-green-600">{{ queueStore.tellerTicketStats.file_delivered }}</p>
                </div>
            </section>

            <section class="rounded-3xl border border-blue-100 bg-white px-5 py-3 shadow-sm" :class="servingNowCollapsed ? '' : 'pb-5'">
                <button
                    type="button"
                    class="flex w-full items-center justify-between gap-3"
                    :aria-expanded="!servingNowCollapsed"
                    aria-controls="serving-now-list"
                    @click="toggleServingNow"
                >
                    <h2 class="text-lg font-bold text-slate-800">قيد الخدمة الآن</h2>
                    <span class="flex items-center gap-2">
                        <span class="rounded-full bg-blue-100 px-2.5 py-0.5 text-xs font-semibold text-blue-700">
                            {{ servingNow.length }}
                        </span>
                        <ChevronDown
                            class="h-5 w-5 text-slate-500 transition-transform"
                            :class="{ 'rotate-180': !servingNowCollapsed }"
                        />
                    </span>
                </button>

                <div v-show="!servingNowCollapsed" id="serving-now-list" class="mt-4">
                    <div v-if="!servingNow.length" class="rounded-2xl border border-dashed border-slate-200 py-10 text-center text-sm text-slate-400">
                        لا يوجد أحد قيد الخدمة حالياً
                    </div>

                    <div v-else class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                        <article
                            v-for="ticket in servingNow"
                            :key="ticket.id"
                            class="rounded-2xl border border-blue-100 bg-blue-50 p-4"
                        >
                            <p class="text-xs font-semibold text-blue-600">تذكرة <span dir="ltr">{{ ticket.ticket_number }}</span></p>
                            <p class="mt-1 text-xl font-black text-slate-900">{{ ticketPersonLabel(ticket) }}</p>
                            <p class="mt-2 text-sm text-slate-600">{{ tellerLabel(ticket) }}</p>
                            <div v-if="ticket.student_kind === 'current_student'" class="mt-3">
                                <TicketDocumentLink :ticket="ticket" preview />
                            </div>
                            <TicketPrintButton class="mt-3" :ticket="ticket" />
                        </article>
                    </div>
                </div>
            </section>

            <section class="rounded-3xl bg-white p-5 shadow-sm">
                <div class="mb-4 flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <h2 class="flex items-center gap-2 text-lg font-bold text-slate-800">
                            <ClipboardList class="h-5 w-5 text-indigo-600" />
                            سجل الطلبات
                        </h2>
                        <p class="mt-1 text-sm text-slate-500">كل الطلبات اليوم — سجّل الخطوات من الكارد أو من مسح QR</p>
                    </div>
                    <span class="w-fit rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600">
                        مسافة: نداء التالي | Enter: إكمال | Esc: إلغاء
                    </span>
                </div>

                <div class="mb-5 flex flex-wrap gap-2">
                    <button
                        class="flex items-center justify-center gap-2 rounded-2xl bg-blue-600 px-4 py-3 font-bold text-white hover:bg-blue-700 disabled:opacity-50"
                        :disabled="!queueStore.isSystemOpen"
                        @click="handleCallNext"
                    >
                        <PhoneCall class="h-5 w-5" />
                        نداء التالي
                    </button>
                    <button
                        type="button"
                        class="flex items-center justify-center gap-2 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 font-bold text-red-700 hover:bg-red-100 disabled:opacity-50"
                        :disabled="restartingCalls"
                        @click="handleRestartCalls"
                    >
                        <VolumeX class="h-5 w-5" />
                        {{ restartingCalls ? 'جاري الإلغاء...' : 'إلغاء النداءات المسجلة' }}
                    </button>
                </div>

                <p v-if="actionMessage" class="mb-4 text-sm text-green-600">{{ actionMessage }}</p>
                <p v-if="actionError" class="mb-4 text-sm text-red-600">{{ actionError }}</p>

                <div class="mb-5 flex flex-col gap-3">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                        <div class="flex min-w-0 flex-1 overflow-hidden rounded-xl border border-slate-200 focus-within:border-indigo-500 focus-within:ring-4 focus-within:ring-indigo-100">
                            <label class="shrink-0">
                                <span class="sr-only">البحث بـ</span>
                                <select
                                    v-model="searchBy"
                                    class="h-full w-28 border-0 bg-slate-50 px-2 py-2.5 text-sm font-semibold text-slate-700 outline-none sm:w-36"
                                >
                                    <option
                                        v-for="opt in searchFieldOptions"
                                        :key="opt.value"
                                        :value="opt.value"
                                    >
                                        {{ opt.label }}
                                    </option>
                                </select>
                            </label>
                            <input
                                v-model="search"
                                type="text"
                                class="min-w-0 flex-1 border-0 border-s border-slate-200 px-3 py-2.5 outline-none"
                                :placeholder="searchPlaceholder"
                            />
                        </div>
                        <label class="block shrink-0 sm:w-56">
                            <span class="sr-only">نوع الطلب</span>
                            <select
                                v-model="typeFilter"
                                class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm font-semibold text-slate-700 outline-none focus:border-indigo-500 focus:ring-4 focus:ring-indigo-100"
                            >
                                <option
                                    v-for="opt in typeOptions"
                                    :key="opt.value"
                                    :value="opt.value"
                                >
                                    {{ opt.label }}
                                </option>
                            </select>
                        </label>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <button
                            v-for="opt in stepOptions"
                            :key="opt.value"
                            type="button"
                            class="rounded-xl px-3 py-2 text-sm font-semibold transition"
                            :class="stepFilter === opt.value
                                ? 'bg-indigo-600 text-white'
                                : 'bg-slate-100 text-slate-600 hover:bg-slate-200'"
                            @click="stepFilter = opt.value"
                        >
                            {{ opt.label }}
                        </button>
                        <button
                            class="flex items-center gap-1 rounded-xl border border-slate-200 px-3 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-50"
                            :disabled="loading"
                            @click="loadTickets"
                        >
                            <RefreshCw class="h-4 w-4" :class="{ 'animate-spin': loading }" />
                        </button>
                    </div>
                </div>

                <p class="mb-3 text-sm text-slate-500">عرض {{ filteredCount }} طلب</p>

                <div class="grid gap-3 sm:grid-cols-2">
                    <article
                        v-for="ticket in queueStore.tellerTickets"
                        :key="ticket.id"
                        class="min-w-0 rounded-2xl border border-slate-100 bg-slate-50/60 p-4"
                        :class="{
                            'border-green-200 bg-green-50/40': ticket.file_delivered,
                            'border-blue-200 bg-blue-50/40': ticket.has_entered && !ticket.file_delivered,
                            'border-orange-200 bg-orange-50/40': ticket.status === 'absent',
                        }"
                    >
                        <div class="mb-3 flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="text-2xl font-black text-indigo-600" dir="ltr">{{ ticket.ticket_number }}</p>
                                <p class="mt-1 break-words font-semibold text-slate-800">{{ ticketPersonLabel(ticket) }}</p>
                            </div>
                            <span
                                class="shrink-0 rounded-full px-2.5 py-1 text-xs font-bold"
                                :class="statusBadgeClass[ticket.status]"
                            >
                                {{ statusLabel[ticket.status] ?? ticket.status }}
                            </span>
                        </div>
                        <dl class="grid gap-x-4 gap-y-2 text-sm sm:grid-cols-2">
                            <div v-if="ticket.student_kind !== 'current_student'" class="flex justify-between gap-3">
                                <dt class="text-slate-500">الرقم القومي</dt>
                                <dd class="font-mono text-slate-700">{{ ticket.national_id }}</dd>
                            </div>
                            <div class="flex justify-between gap-3">
                                <dt class="text-slate-500">نوع الطلب</dt>
                                <dd class="text-slate-700">{{ ticket.request_type_label ?? ticket.student_kind_label ?? '—' }}</dd>
                            </div>
                            <div class="flex justify-between gap-3">
                                <dt class="text-slate-500">الكلية</dt>
                                <dd class="text-slate-700">{{ ticket.college_label ?? '—' }}</dd>
                            </div>
                            <div v-if="ticket.student_kind === 'current_student'" class="flex justify-between gap-3">
                                <dt class="text-slate-500">القسم</dt>
                                <dd class="text-slate-700">{{ ticket.department ?? '—' }}</dd>
                            </div>
                            <div v-if="ticket.student_kind === 'current_student'" class="flex justify-between gap-3">
                                <dt class="text-slate-500">رقم الجلوس</dt>
                                <dd class="font-mono text-slate-700">{{ ticket.seat_number ?? '—' }}</dd>
                            </div>
                            <div v-else-if="ticket.order_number" class="flex justify-between gap-3">
                                <dt class="text-slate-500">رقم الطلب</dt>
                                <dd class="text-slate-700">{{ ticket.order_number }}</dd>
                            </div>
                            <div v-if="ticket.student_kind === 'current_student'" class="sm:col-span-2">
                                <dt class="mb-2 text-slate-500">{{ ticket.document_kind_label ?? 'المستند' }}</dt>
                                <dd><TicketDocumentLink :ticket="ticket" preview /></dd>
                            </div>
                            <div class="flex justify-between gap-3 sm:col-span-2">
                                <dt class="text-slate-500">موظف الشباك</dt>
                                <dd class="text-slate-700">{{ tellerLabel(ticket) }}</dd>
                            </div>
                        </dl>
                        <div class="mt-4 space-y-2 border-t border-slate-200/80 pt-3">
                            <div
                                v-if="canCall(ticket)"
                                class="grid gap-2"
                                :class="ticket.status === 'waiting' ? 'grid-cols-2' : 'grid-cols-1'"
                            >
                                <button
                                    class="flex items-center justify-center gap-1.5 rounded-xl bg-blue-600 px-3 py-2.5 text-sm font-bold text-white hover:bg-blue-700 disabled:opacity-60"
                                    :disabled="callingId === ticket.id || !queueStore.isSystemOpen"
                                    @click="handleCallTicket(ticket)"
                                >
                                    <PhoneCall class="h-4 w-4" />
                                    {{ callingId === ticket.id ? 'جاري...' : 'نداء' }}
                                </button>
                                <button
                                    v-if="ticket.status === 'waiting'"
                                    class="flex items-center justify-center gap-1.5 rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm font-bold text-slate-700 hover:bg-slate-100 disabled:opacity-60"
                                    :disabled="skippingId === ticket.id"
                                    @click="handleSkipTicket(ticket)"
                                >
                                    <SkipForward class="h-4 w-4" />
                                    {{ skippingId === ticket.id ? 'جاري...' : 'تخطي' }}
                                </button>
                            </div>
                            <button
                                v-if="canRecall(ticket)"
                                class="flex w-full items-center justify-center gap-1.5 rounded-xl bg-indigo-600 px-3 py-2.5 text-sm font-bold text-white hover:bg-indigo-700 disabled:opacity-60"
                                :disabled="recallingId === ticket.id"
                                @click="handleRecall(ticket)"
                            >
                                <BellRing class="h-4 w-4" />
                                {{ recallingId === ticket.id ? 'جاري...' : 'إعادة نداء' }}
                            </button>
                            <TicketPrintButton :ticket="ticket" />
                            <TicketProcessActions
                                :ticket="ticket"
                                :busy-id="processBusyId"
                                :busy-step="busyStep"
                                :system-open="queueStore.isSystemOpen"
                                :allowed-steps="authStore.allowedProcessSteps"
                                compact
                                @mark="handleProcessMark(ticket, $event)"
                            />
                            <button
                                v-if="canMarkAbsent(ticket)"
                                class="flex w-full items-center justify-center gap-1.5 rounded-xl bg-orange-600 px-3 py-2.5 text-sm font-bold text-white hover:bg-orange-700 disabled:opacity-60"
                                :disabled="absentId === ticket.id"
                                @click="handleMarkAbsent(ticket)"
                            >
                                <UserX class="h-4 w-4" />
                                {{ absentId === ticket.id ? 'جاري...' : 'مش موجود' }}
                            </button>
                            <button
                                v-else-if="ticket.status === 'absent'"
                                class="flex w-full items-center justify-center gap-1.5 rounded-xl bg-emerald-600 px-3 py-2.5 text-sm font-bold text-white hover:bg-emerald-700 disabled:opacity-60"
                                :disabled="restoringId === ticket.id"
                                @click="handleRestore(ticket)"
                            >
                                <RotateCcw class="h-4 w-4" />
                                {{ restoringId === ticket.id ? 'جاري الإرجاع...' : 'إرجاع للطابور' }}
                            </button>
                            <TicketEditForm
                                :ticket="ticket"
                                :busy="editingId === ticket.id"
                                @save="handleEdit"
                            />
                            <TicketDeleteButton
                                :ticket="ticket"
                                :busy="deletingId === ticket.id"
                                @delete="handleDelete"
                            />
                        </div>
                    </article>
                    <p
                        v-if="!queueStore.tellerTickets.length"
                        class="col-span-full py-12 text-center text-slate-400"
                    >
                        لا توجد طلبات مطابقة
                    </p>
                </div>
            </section>

            <section class="rounded-3xl bg-white p-6 shadow-sm">
                <div class="mb-4 flex items-center gap-2">
                    <UserX class="h-5 w-5 text-orange-600" />
                    <h2 class="text-lg font-bold text-slate-800">تم نداؤهم ولم يحضروا</h2>
                    <span class="rounded-full bg-orange-100 px-2.5 py-0.5 text-xs font-semibold text-orange-700">
                        {{ queueStore.absentTickets.length }}
                    </span>
                </div>

                <div v-if="!queueStore.absentTickets.length" class="rounded-2xl border border-dashed border-slate-200 py-10 text-center text-sm text-slate-400">
                    لا يوجد أحد مسجّل كـ "مش موجود"
                </div>

                <div v-else class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                    <article
                        v-for="ticket in queueStore.absentTickets"
                        :key="ticket.id"
                        class="rounded-2xl border border-orange-100 bg-orange-50/50 p-4"
                    >
                        <div class="mb-3 flex items-start justify-between gap-3">
                            <div>
                                <p class="text-2xl font-black text-orange-600" dir="ltr">{{ ticket.ticket_number }}</p>
                                <p class="mt-1 font-semibold text-slate-800">{{ ticketPersonLabel(ticket) }}</p>
                            </div>
                        </div>
                        <dl class="mb-4 space-y-2 text-sm">
                            <div class="flex justify-between gap-3">
                                <dt class="text-slate-500">نوع الطلب</dt>
                                <dd class="text-slate-700">{{ ticket.request_type_label ?? ticket.student_kind_label ?? '—' }}</dd>
                            </div>
                            <div class="flex justify-between gap-3">
                                <dt class="text-slate-500">الكلية</dt>
                                <dd class="text-slate-700">{{ ticket.college_label ?? '—' }}</dd>
                            </div>
                            <div v-if="ticket.student_kind === 'current_student'" class="flex justify-between gap-3">
                                <dt class="text-slate-500">القسم</dt>
                                <dd class="text-slate-700">{{ ticket.department ?? '—' }}</dd>
                            </div>
                            <div v-if="ticket.student_kind === 'current_student'" class="flex justify-between gap-3">
                                <dt class="text-slate-500">رقم الجلوس</dt>
                                <dd class="font-mono text-slate-700">{{ ticket.seat_number ?? '—' }}</dd>
                            </div>
                            <div v-else-if="ticket.order_number" class="flex justify-between gap-3">
                                <dt class="text-slate-500">رقم الطلب</dt>
                                <dd class="text-slate-700">{{ ticket.order_number }}</dd>
                            </div>
                            <div v-if="ticket.student_kind === 'current_student'">
                                <dt class="mb-2 text-slate-500">المستند</dt>
                                <dd><TicketDocumentLink :ticket="ticket" preview /></dd>
                            </div>
                            <div class="flex justify-between gap-3">
                                <dt class="text-slate-500">وقت النداء</dt>
                                <dd class="text-slate-700">{{ formatTime(ticket.called_at) }}</dd>
                            </div>
                            <div class="flex justify-between gap-3">
                                <dt class="text-slate-500">موظف الشباك</dt>
                                <dd class="text-slate-700">{{ tellerLabel(ticket) }}</dd>
                            </div>
                        </dl>
                        <TicketPrintButton class="mb-2" :ticket="ticket" />
                        <button
                            class="flex w-full items-center justify-center gap-2 rounded-xl bg-emerald-600 px-3 py-2.5 text-sm font-bold text-white hover:bg-emerald-700 disabled:opacity-60"
                            :disabled="restoringId === ticket.id"
                            @click="handleRestore(ticket)"
                        >
                            <RotateCcw class="h-4 w-4" />
                            {{ restoringId === ticket.id ? 'جاري الإرجاع...' : 'إرجاع للطابور' }}
                        </button>
                        <TicketEditForm
                            class="mt-2"
                            :ticket="ticket"
                            :busy="editingId === ticket.id"
                            @save="handleEdit"
                        />
                        <TicketDeleteButton
                            class="mt-2"
                            :ticket="ticket"
                            :busy="deletingId === ticket.id"
                            @delete="handleDelete"
                        />
                    </article>
                </div>
            </section>
        </main>

        <StaffIssueTicketModal
            v-if="showIssueForm"
            :require-name="issueRequiresName"
            @issued="handleIssued"
            @close="showIssueForm = false"
        />

        <IssuedTicketModal
            v-if="issuedTicket"
            :ticket="issuedTicket"
            :tickets="issuedTickets"
            allow-print
            :allow-download="false"
            :show-admission-links="false"
            :auto-print="!issueRequiresName"
            heading="تم إصدار الدور"
            @close="closeIssuedModal"
        />
    </AppNavbar>
</template>
