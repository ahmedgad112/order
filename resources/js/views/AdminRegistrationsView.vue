<script setup>
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import {
    Archive,
    CalendarDays,
    RefreshCw,
    Search,
} from 'lucide-vue-next';
import { useQueueStore } from '../stores/queueStore';
import AppNavbar from '../components/AppNavbar.vue';
import TicketProcessActions from '../components/TicketProcessActions.vue';
import TicketDeleteButton from '../components/TicketDeleteButton.vue';
import TicketEditForm from '../components/TicketEditForm.vue';
import TicketDocumentLink from '../components/TicketDocumentLink.vue';

const queueStore = useQueueStore();

const search = ref('');
const stepFilter = ref('all');
const selectedDate = ref('');
const loading = ref(false);

const processStepValues = ['entered', 'paid', 'file_withdrawn', 'documents_reviewed', 'medical_checked', 'face_printed', 'file_delivered'];
const stepOptions = [
    { value: 'all', label: 'الكل' },
    { value: 'entered', label: 'طلب دخول' },
    { value: 'paid', label: 'دفع' },
    { value: 'file_withdrawn', label: 'سحب ملف' },
    { value: 'documents_reviewed', label: 'مراجعة ورق' },
    { value: 'medical_checked', label: 'كشف طبي' },
    { value: 'face_printed', label: 'بصمة وجه' },
    { value: 'file_delivered', label: 'تسليم الملف' },
    { value: 'completed', label: 'دخل' },
    { value: 'cancelled', label: 'ملغى' },
    { value: 'absent', label: 'مش موجود' },
];
const processBusyId = ref(null);
const busyStep = ref(null);
const deletingId = ref(null);
const editingId = ref(null);
const feedback = ref('');
const actionError = ref('');

const statusBadgeClass = {
    waiting: 'bg-amber-100 text-amber-800',
    serving: 'bg-blue-100 text-blue-800',
    completed: 'bg-green-100 text-green-800',
    cancelled: 'bg-red-100 text-red-800',
    absent: 'bg-orange-100 text-orange-800',
};

const filteredCount = computed(() => queueStore.registrations.length);
const isToday = computed(() => queueStore.registrationIsToday);
const recentArchiveDates = computed(() => queueStore.registrationDates.slice(0, 8));
const pageSubtitle = computed(() => {
    if (!isToday.value && queueStore.registrationDate) {
        return `أرشيف التسجيلات — ${formatArchiveDate(queueStore.registrationDate)}`;
    }

    return 'كل الأشخاص المسجلين اليوم — ويمكن فتح أرشيف الأيام السابقة';
});

function listParams() {
    const params = {
        search: search.value.trim() || undefined,
        date: selectedDate.value || undefined,
    };

    if (processStepValues.includes(stepFilter.value)) {
        params.step = stepFilter.value;
    } else if (stepFilter.value !== 'all') {
        params.status = stepFilter.value;
    }

    return params;
}

function formatTime(iso) {
    if (!iso) return '—';
    return new Date(iso).toLocaleTimeString('ar-EG', { hour: '2-digit', minute: '2-digit' });
}

function formatArchiveDate(date) {
    if (!date) return '';
    return new Date(`${date}T12:00:00`).toLocaleDateString('ar-EG', {
        weekday: 'long',
        year: 'numeric',
        month: 'long',
        day: 'numeric',
    });
}

function formatRegisteredAt(iso) {
    if (!iso) return '—';
    if (isToday.value) {
        return formatTime(iso);
    }

    return new Date(iso).toLocaleString('ar-EG', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

function selectDate(date) {
    selectedDate.value = date;
    loadTickets();
}

function onDateInput(event) {
    selectDate(event.target.value);
}

function tellerLabel(ticket) {
    if (ticket.teller_name && ticket.counter_name && ticket.teller_name !== ticket.counter_name) {
        return `${ticket.teller_name} — ${ticket.counter_name}`;
    }

    return ticket.teller_name || ticket.counter_name || '—';
}

async function loadTickets(silent = false) {
    if (!silent) {
        loading.value = true;
    }
    try {
        await queueStore.fetchRegistrations(listParams());
        if (queueStore.registrationDate) {
            selectedDate.value = queueStore.registrationDate;
        }
    } finally {
        if (!silent) {
            loading.value = false;
        }
    }
}

async function handleProcessMark(ticket, step) {
    processBusyId.value = ticket.id;
    busyStep.value = step;
    feedback.value = '';
    actionError.value = '';
    try {
        const result = await queueStore.markProcessStep(ticket.id, step);
        feedback.value = result.message;
        await loadTickets();
    } catch (err) {
        actionError.value = err.response?.data?.message
            ?? err.response?.data?.errors?.ticket?.[0]
            ?? 'تعذر تحديث الطلب.';
    } finally {
        processBusyId.value = null;
        busyStep.value = null;
    }
}

async function handleDelete(ticket) {
    deletingId.value = ticket.id;
    feedback.value = '';
    actionError.value = '';
    try {
        const result = await queueStore.deleteTicket(ticket.id);
        feedback.value = result.message;
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
    feedback.value = '';
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
        feedback.value = result.message;
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

function onQueueUpdate() {
    if (isToday.value) {
        loadTickets();
    }
}

let searchTimer = null;

watch(stepFilter, () => loadTickets());

watch(search, () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => loadTickets(), 400);
});

let unsubscribeEcho = null;
let stopAutoRefresh = null;

onMounted(() => {
    loadTickets();

    unsubscribeEcho = queueStore.subscribeEcho({
        TicketIssued: onQueueUpdate,
        TicketCalled: onQueueUpdate,
        TicketCompleted: onQueueUpdate,
        TicketAbsent: onQueueUpdate,
        TicketRestored: onQueueUpdate,
        TicketDeleted: onQueueUpdate,
        TicketUpdated: onQueueUpdate,
        QueueDayReset: onQueueUpdate,
    });

    stopAutoRefresh = queueStore.startAutoRefresh(() => {
        if (isToday.value) {
            loadTickets(true);
        }
    }, 5000);
});

onUnmounted(() => {
    unsubscribeEcho?.();
    stopAutoRefresh?.();
    clearTimeout(searchTimer);
});
</script>

<template>
    <div class="min-h-screen bg-slate-100">
        <AppNavbar title="سجل التسجيلات" :subtitle="pageSubtitle" />

        <main class="mx-auto max-w-7xl space-y-5 px-4 py-6 sm:px-6">
            <section class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5">
                <div class="rounded-2xl bg-white p-4 shadow-sm">
                    <p class="text-xs text-slate-500">الإجمالي</p>
                    <p class="text-2xl font-black text-slate-800">{{ queueStore.registrationStats.total }}</p>
                </div>
                <div class="rounded-2xl bg-amber-50 p-4 shadow-sm">
                    <p class="text-xs text-amber-700">انتظار</p>
                    <p class="text-2xl font-black text-amber-600">{{ queueStore.registrationStats.waiting }}</p>
                </div>
                <div class="rounded-2xl bg-blue-50 p-4 shadow-sm">
                    <p class="text-xs text-blue-700">طلب دخول</p>
                    <p class="text-2xl font-black text-blue-600">{{ queueStore.registrationStats.entered }}</p>
                </div>
                <div class="rounded-2xl bg-cyan-50 p-4 shadow-sm">
                    <p class="text-xs text-cyan-700">دفع</p>
                    <p class="text-2xl font-black text-cyan-600">{{ queueStore.registrationStats.paid }}</p>
                </div>
                <div class="rounded-2xl bg-orange-50 p-4 shadow-sm">
                    <p class="text-xs text-orange-700">سحب ملف</p>
                    <p class="text-2xl font-black text-orange-600">{{ queueStore.registrationStats.file_withdrawn }}</p>
                </div>
                <div class="rounded-2xl bg-indigo-50 p-4 shadow-sm">
                    <p class="text-xs text-indigo-700">مراجعة ورق</p>
                    <p class="text-2xl font-black text-indigo-600">{{ queueStore.registrationStats.documents_reviewed }}</p>
                </div>
                <div class="rounded-2xl bg-teal-50 p-4 shadow-sm">
                    <p class="text-xs text-teal-700">كشف طبي</p>
                    <p class="text-2xl font-black text-teal-600">{{ queueStore.registrationStats.medical_checked }}</p>
                </div>
                <div class="rounded-2xl bg-violet-50 p-4 shadow-sm">
                    <p class="text-xs text-violet-700">بصمة وجه</p>
                    <p class="text-2xl font-black text-violet-600">{{ queueStore.registrationStats.face_printed }}</p>
                </div>
                <div class="rounded-2xl bg-green-50 p-4 shadow-sm">
                    <p class="text-xs text-green-700">تسليم الملف</p>
                    <p class="text-2xl font-black text-green-600">{{ queueStore.registrationStats.file_delivered }}</p>
                </div>
            </section>

            <section class="rounded-3xl bg-white p-5 shadow-sm">
                <div class="mb-4 flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <h2 class="flex items-center gap-2 text-lg font-bold text-slate-800">
                            <Archive class="h-5 w-5 text-indigo-600" />
                            الأرشيف
                        </h2>
                        <p class="mt-1 text-sm text-slate-500">اختر يوماً سابقاً لعرض الأشخاص الذين سجلوا فيه</p>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <button
                            type="button"
                            class="rounded-xl px-3 py-2 text-sm font-semibold transition"
                            :class="isToday
                                ? 'bg-indigo-600 text-white'
                                : 'bg-slate-100 text-slate-600 hover:bg-slate-200'"
                            @click="selectDate(queueStore.registrationToday)"
                        >
                            اليوم
                        </button>
                        <label class="flex items-center gap-2 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-sm font-semibold text-slate-700">
                            <CalendarDays class="h-4 w-4 text-indigo-600" />
                            <span class="sr-only">تاريخ الأرشيف</span>
                            <input
                                type="date"
                                class="bg-transparent text-slate-800 outline-none"
                                :value="selectedDate"
                                :max="queueStore.registrationToday || undefined"
                                @change="onDateInput"
                            />
                        </label>
                    </div>
                </div>
                <div v-if="recentArchiveDates.length" class="flex flex-wrap gap-2">
                    <button
                        v-for="date in recentArchiveDates"
                        :key="date"
                        type="button"
                        class="rounded-xl px-3 py-1.5 text-xs font-semibold transition"
                        :class="selectedDate === date
                            ? 'bg-indigo-600 text-white'
                            : 'bg-slate-100 text-slate-600 hover:bg-slate-200'"
                        @click="selectDate(date)"
                    >
                        {{ formatArchiveDate(date) }}
                    </button>
                </div>
            </section>

            <section class="rounded-3xl bg-white p-5 shadow-sm">
                <div class="mb-5 flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                    <div class="relative flex-1">
                        <Search class="absolute right-3 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-400" />
                        <input
                            v-model="search"
                            type="text"
                            class="w-full rounded-xl border border-slate-200 py-2.5 pr-10 pl-4 outline-none focus:border-indigo-500 focus:ring-4 focus:ring-indigo-100"
                            placeholder="بحث بالاسم، الرقم القومي، رقم الطلب، أو رقم التذكرة..."
                        />
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

                <p v-if="feedback" class="mb-4 rounded-xl bg-green-50 px-4 py-2 text-sm font-semibold text-green-700">
                    {{ feedback }}
                </p>
                <p v-if="actionError" class="mb-4 rounded-xl bg-red-50 px-4 py-2 text-sm font-semibold text-red-700">
                    {{ actionError }}
                </p>

                <p class="mb-3 text-sm text-slate-500">عرض {{ filteredCount }} تسجيل</p>

                <div class="grid gap-3 sm:grid-cols-2">
                    <article
                        v-for="ticket in queueStore.registrations"
                        :key="ticket.id"
                        class="rounded-2xl border border-slate-100 bg-slate-50/60 p-4"
                        :class="{ 'border-green-200 bg-green-50/40': ticket.status === 'completed' }"
                    >
                        <div class="mb-3 flex items-start justify-between gap-3">
                            <div>
                                <p class="text-2xl font-black text-indigo-600" dir="ltr">{{ ticket.ticket_number }}</p>
                                <p class="mt-1 font-semibold text-slate-800">{{ ticket.full_name }}</p>
                            </div>
                            <span
                                class="shrink-0 rounded-full px-2.5 py-1 text-xs font-bold"
                                :class="statusBadgeClass[ticket.status]"
                            >
                                {{ ticket.status_label }}
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
                            <div v-else class="flex justify-between gap-3">
                                <dt class="text-slate-500">رقم الطلب</dt>
                                <dd class="text-slate-700">{{ ticket.order_number }}</dd>
                            </div>
                            <div v-if="ticket.student_kind === 'current_student'" class="sm:col-span-2">
                                <dt class="mb-2 text-slate-500">{{ ticket.document_kind_label ?? 'المستند' }}</dt>
                                <dd><TicketDocumentLink :ticket="ticket" preview /></dd>
                            </div>
                            <div class="flex justify-between gap-3">
                                <dt class="text-slate-500">طلب دخول</dt>
                                <dd class="font-semibold" :class="ticket.has_entered ? 'text-blue-700' : 'text-slate-400'">
                                    {{ ticket.has_entered ? 'تم' : '—' }}
                                </dd>
                            </div>
                            <div v-if="ticket.student_kind === 'current_student'" class="flex justify-between gap-3">
                                <dt class="text-slate-500">مراجعة ورق</dt>
                                <dd class="font-semibold" :class="ticket.has_documents_reviewed ? 'text-indigo-700' : 'text-slate-400'">
                                    {{ ticket.has_documents_reviewed ? 'تم' : '—' }}
                                </dd>
                            </div>
                            <template v-else>
                                <div class="flex justify-between gap-3">
                                    <dt class="text-slate-500">دفع</dt>
                                    <dd class="font-semibold" :class="ticket.has_paid ? 'text-cyan-700' : 'text-slate-400'">
                                        {{ ticket.has_paid ? 'تم' : '—' }}
                                    </dd>
                                </div>
                                <div class="flex justify-between gap-3">
                                    <dt class="text-slate-500">سحب ملف</dt>
                                    <dd class="font-semibold" :class="ticket.has_file_withdrawn ? 'text-orange-700' : 'text-slate-400'">
                                        {{ ticket.has_file_withdrawn ? 'تم' : '—' }}
                                    </dd>
                                </div>
                                <div class="flex justify-between gap-3">
                                    <dt class="text-slate-500">كشف طبي</dt>
                                    <dd class="font-semibold" :class="ticket.has_medical_checked ? 'text-teal-700' : 'text-slate-400'">
                                        {{ ticket.has_medical_checked ? 'تم' : '—' }}
                                    </dd>
                                </div>
                            </template>
                            <div v-if="ticket.student_kind !== 'current_student'" class="flex justify-between gap-3">
                                <dt class="text-slate-500">بصمة وجه</dt>
                                <dd class="font-semibold" :class="ticket.has_face_printed ? 'text-violet-700' : 'text-slate-400'">
                                    {{ ticket.has_face_printed ? 'تم' : '—' }}
                                </dd>
                            </div>
                            <div class="flex justify-between gap-3">
                                <dt class="text-slate-500">تسليم الملف</dt>
                                <dd class="font-semibold" :class="ticket.file_delivered ? 'text-green-700' : 'text-slate-400'">
                                    {{ ticket.file_delivered ? 'تم' : '—' }}
                                </dd>
                            </div>
                            <div class="flex justify-between gap-3">
                                <dt class="text-slate-500">موظف الشباك</dt>
                                <dd class="text-slate-700">{{ tellerLabel(ticket) }}</dd>
                            </div>
                            <div class="flex justify-between gap-3">
                                <dt class="text-slate-500">وقت التسجيل</dt>
                                <dd class="text-slate-700">{{ formatRegisteredAt(ticket.created_at) }}</dd>
                            </div>
                        </dl>
                        <div class="mt-4 space-y-2 border-t border-slate-200/80 pt-3">
                            <TicketProcessActions
                                v-if="isToday"
                                :ticket="ticket"
                                :busy-id="processBusyId"
                                :busy-step="busyStep"
                                :system-open="queueStore.isSystemOpen"
                                compact
                                @mark="handleProcessMark(ticket, $event)"
                            />
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
                        v-if="!queueStore.registrations.length"
                        class="col-span-full py-12 text-center text-slate-400"
                    >
                        {{ isToday && !search ? 'لا توجد تسجيلات اليوم' : 'لا توجد تسجيلات مطابقة' }}
                    </p>
                </div>
            </section>
        </main>
    </div>
</template>
