<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { useRoute } from 'vue-router';
import {
    BellRing,
    CheckCircle2,
    Clock,
    FileText,
    GraduationCap,
    Hash,
    RefreshCw,
    User,
    XCircle,
} from 'lucide-vue-next';
import { useAuthStore } from '../stores/authStore';
import { useQueueStore } from '../stores/queueStore';
import AppNavbar from '../components/AppNavbar.vue';
import TicketProcessActions from '../components/TicketProcessActions.vue';
import TicketDocumentLink from '../components/TicketDocumentLink.vue';

const route = useRoute();
const authStore = useAuthStore();
const queueStore = useQueueStore();

const ticket = ref(null);
const loadError = ref('');
const isRefreshing = ref(false);
const actionMessage = ref('');
const actionError = ref('');
const busyStep = ref(null);

const statusConfig = computed(() => {
    if (!ticket.value) {
        return null;
    }

    const map = {
        waiting: {
            color: 'amber',
            icon: Clock,
            title: 'في قائمة الانتظار',
            hint: 'يمكن تسجيل خطوات الطلب من الأزرار بالأسفل',
        },
        serving: {
            color: 'blue',
            icon: BellRing,
            title: 'قيد الخدمة',
            hint: 'أكمل خطوات الطلب بالترتيب',
        },
        completed: {
            color: 'green',
            icon: CheckCircle2,
            title: 'اكتملت الخدمة',
            hint: 'تم إنهاء كل خطوات الطلب',
        },
        cancelled: {
            color: 'red',
            icon: XCircle,
            title: 'تم إلغاء التذكرة',
            hint: 'لا يمكن تحديث هذا الطلب',
        },
        absent: {
            color: 'orange',
            icon: XCircle,
            title: 'تم نداؤه ولم يحضر',
            hint: 'يمكن إرجاعه للطابور من لوحة الموظف',
        },
    };

    return map[ticket.value.status] ?? map.waiting;
});

const navbarSubtitle = computed(() => (
    [authStore.user?.role_label, authStore.user?.name].filter(Boolean).join(' — ')
));

async function loadTicket(silent = false) {
    if (!silent) {
        loadError.value = '';
    } else {
        isRefreshing.value = true;
    }

    try {
        ticket.value = await queueStore.fetchScannedTicket(route.params.token, { silent });
        loadError.value = '';
    } catch {
        ticket.value = null;
        loadError.value = queueStore.error || 'لم يتم العثور على التذكرة.';
    } finally {
        isRefreshing.value = false;
    }
}

async function handleProcessMark(step) {
    if (!ticket.value) {
        return;
    }

    busyStep.value = step;
    actionError.value = '';
    try {
        const result = await queueStore.markProcessStep(ticket.value.id, step);
        actionMessage.value = result.message;
        ticket.value = {
            ...ticket.value,
            ...result.ticket,
            status_label: ticket.value.status_label,
        };
        await loadTicket(true);
    } catch (err) {
        actionError.value = err.response?.data?.message
            ?? err.response?.data?.errors?.ticket?.[0]
            ?? 'تعذر تحديث الطلب.';
    } finally {
        busyStep.value = null;
    }
}

function onQueueEvent() {
    if (ticket.value) {
        loadTicket(true);
    }
}

let unsubscribeEcho = null;
let stopAutoRefresh = null;

onMounted(async () => {
    await loadTicket();

    unsubscribeEcho = queueStore.subscribeEcho({
        TicketIssued: onQueueEvent,
        TicketCalled: onQueueEvent,
        TicketCompleted: onQueueEvent,
        TicketAbsent: onQueueEvent,
        TicketRestored: onQueueEvent,
        TicketDeleted: () => {
            loadTicket(true);
        },
        QueueDayReset: () => {
            loadTicket(true);
        },
    });

    stopAutoRefresh = queueStore.startAutoRefresh(async () => {
        if (ticket.value) {
            await loadTicket(true);
        }
    });
});

onUnmounted(() => {
    unsubscribeEcho?.();
    stopAutoRefresh?.();
});
</script>

<template>
    <div class="min-h-screen bg-gradient-to-br from-blue-50 via-white to-indigo-50">
        <AppNavbar title="بيانات التذكرة" :subtitle="navbarSubtitle" max-width="3xl" />

        <main class="mx-auto max-w-3xl space-y-6 px-4 py-8 sm:px-6 sm:py-10">
            <div
                v-if="queueStore.loading && !ticket"
                class="rounded-3xl border border-slate-200 bg-white p-10 text-center text-slate-500 shadow-xl"
            >
                جاري تحميل بيانات التذكرة...
            </div>

            <div
                v-else-if="loadError"
                class="rounded-3xl border border-red-200 bg-red-50 p-8 text-center shadow-xl"
            >
                <XCircle class="mx-auto mb-3 h-10 w-10 text-red-500" />
                <h2 class="text-xl font-bold text-red-800">تعذر عرض التذكرة</h2>
                <p class="mt-2 text-red-700">{{ loadError }}</p>
            </div>

            <div
                v-else-if="ticket && statusConfig"
                class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-xl"
            >
                <div
                    class="px-8 py-6 text-center text-white"
                    :class="{
                        'bg-amber-500': statusConfig.color === 'amber',
                        'bg-blue-600': statusConfig.color === 'blue',
                        'bg-green-600': statusConfig.color === 'green',
                        'bg-red-500': statusConfig.color === 'red',
                        'bg-orange-500': statusConfig.color === 'orange',
                    }"
                >
                    <component :is="statusConfig.icon" class="mx-auto mb-3 h-10 w-10" />
                    <h3 class="text-xl font-bold">{{ statusConfig.title }}</h3>
                    <p class="mt-1 text-sm opacity-90">{{ statusConfig.hint }}</p>
                </div>

                <div class="p-6 sm:p-8">
                    <p class="text-center text-sm text-slate-500">رقم التذكرة</p>
                    <p class="my-3 text-center text-7xl font-black text-blue-600" dir="ltr">{{ ticket.ticket_number }}</p>
                    <div class="flex justify-center">
                        <span
                            class="rounded-full px-4 py-1.5 text-sm font-bold"
                            :class="{
                                'bg-amber-100 text-amber-800': ticket.status === 'waiting',
                                'bg-blue-100 text-blue-800': ticket.status === 'serving',
                                'bg-green-100 text-green-800': ticket.status === 'completed',
                                'bg-red-100 text-red-800': ticket.status === 'cancelled',
                                'bg-orange-100 text-orange-800': ticket.status === 'absent',
                            }"
                        >
                            {{ ticket.status_label }}
                        </span>
                    </div>

                    <dl class="mt-8 grid gap-3">
                        <div class="flex items-start justify-between gap-4 rounded-2xl bg-slate-50 px-4 py-4">
                            <dt class="flex items-center gap-2 text-sm font-semibold text-slate-500">
                                <User class="h-4 w-4" />
                                الاسم الكامل
                            </dt>
                            <dd class="text-base font-bold text-slate-900">{{ ticket.full_name }}</dd>
                        </div>
                        <div v-if="ticket.student_kind !== 'current_student'" class="flex items-start justify-between gap-4 rounded-2xl bg-slate-50 px-4 py-4">
                            <dt class="flex items-center gap-2 text-sm font-semibold text-slate-500">
                                <Hash class="h-4 w-4" />
                                الرقم القومي
                            </dt>
                            <dd class="font-mono text-base font-bold tracking-wide text-slate-900" dir="ltr">
                                {{ ticket.national_id }}
                            </dd>
                        </div>
                        <div class="flex items-start justify-between gap-4 rounded-2xl bg-slate-50 px-4 py-4">
                            <dt class="flex items-center gap-2 text-sm font-semibold text-slate-500">
                                <FileText class="h-4 w-4" />
                                نوع الطلب
                            </dt>
                            <dd class="text-base font-bold text-slate-900">{{ ticket.request_type_label ?? ticket.student_kind_label ?? '—' }}</dd>
                        </div>
                        <div class="flex items-start justify-between gap-4 rounded-2xl bg-slate-50 px-4 py-4">
                            <dt class="flex items-center gap-2 text-sm font-semibold text-slate-500">
                                <GraduationCap class="h-4 w-4" />
                                الكلية
                            </dt>
                            <dd class="text-base font-bold text-slate-900">{{ ticket.college_label ?? '—' }}</dd>
                        </div>
                        <div v-if="ticket.student_kind === 'current_student'" class="flex items-start justify-between gap-4 rounded-2xl bg-slate-50 px-4 py-4">
                            <dt class="flex items-center gap-2 text-sm font-semibold text-slate-500">
                                <FileText class="h-4 w-4" />
                                القسم
                            </dt>
                            <dd class="text-base font-bold text-slate-900">{{ ticket.department ?? '—' }}</dd>
                        </div>
                        <div v-if="ticket.student_kind === 'current_student'" class="flex items-start justify-between gap-4 rounded-2xl bg-slate-50 px-4 py-4">
                            <dt class="flex items-center gap-2 text-sm font-semibold text-slate-500">
                                <Hash class="h-4 w-4" />
                                رقم الجلوس
                            </dt>
                            <dd class="font-mono text-base font-bold text-slate-900">{{ ticket.seat_number ?? '—' }}</dd>
                        </div>
                        <div v-else class="flex items-start justify-between gap-4 rounded-2xl bg-slate-50 px-4 py-4">
                            <dt class="flex items-center gap-2 text-sm font-semibold text-slate-500">
                                <FileText class="h-4 w-4" />
                                رقم الطلب
                            </dt>
                            <dd class="text-base font-bold text-slate-900">{{ ticket.order_number }}</dd>
                        </div>
                        <div v-if="ticket.student_kind === 'current_student'" class="rounded-2xl bg-slate-50 px-4 py-4">
                            <dt class="mb-3 flex items-center gap-2 text-sm font-semibold text-slate-500">
                                <FileText class="h-4 w-4" />
                                {{ ticket.document_kind_label ?? 'المستند' }}
                            </dt>
                            <dd><TicketDocumentLink :ticket="ticket" preview /></dd>
                        </div>
                    </dl>

                    <div class="mt-6 rounded-2xl border border-slate-200 bg-slate-50 p-4">
                        <p class="mb-3 text-sm font-bold text-slate-700">خطوات الطلب</p>
                        <p v-if="actionMessage" class="mb-3 text-sm font-semibold text-green-600">{{ actionMessage }}</p>
                        <p v-if="actionError" class="mb-3 text-sm font-semibold text-red-600">{{ actionError }}</p>
                        <TicketProcessActions
                            :ticket="ticket"
                            :busy-id="ticket.id"
                            :busy-step="busyStep"
                            :system-open="queueStore.isSystemOpen"
                            @mark="handleProcessMark"
                        />
                    </div>

                    <div v-if="ticket.status === 'waiting'" class="mt-6 grid grid-cols-2 gap-4">
                        <div class="rounded-2xl bg-amber-50 p-4 text-center">
                            <p class="text-sm text-amber-700">الترتيب</p>
                            <p class="text-3xl font-black text-amber-600">{{ ticket.position_in_queue }}</p>
                        </div>
                        <div class="rounded-2xl bg-slate-50 p-4 text-center">
                            <p class="text-sm text-slate-500">أمامه</p>
                            <p class="text-3xl font-black text-slate-700">{{ ticket.people_ahead }}</p>
                            <p class="text-xs text-slate-400">تذكرة</p>
                        </div>
                    </div>

                    <div
                        v-if="ticket.status === 'serving' && (ticket.teller_name || ticket.counter_name)"
                        class="mt-6 rounded-2xl bg-blue-50 p-5 text-center"
                    >
                        <p class="text-sm text-blue-600">موظف الشباك</p>
                        <p class="text-3xl font-black text-blue-700">
                            {{ ticket.teller_name || ticket.counter_name }}
                        </p>
                        <p
                            v-if="ticket.teller_name && ticket.counter_name && ticket.teller_name !== ticket.counter_name"
                            class="mt-1 text-sm font-semibold text-blue-600"
                        >
                            {{ ticket.counter_name }}
                        </p>
                    </div>

                    <button
                        class="mt-8 flex w-full items-center justify-center gap-2 rounded-2xl border border-slate-200 py-3 text-sm font-semibold text-slate-600 hover:bg-slate-50"
                        :disabled="isRefreshing"
                        @click="loadTicket(true)"
                    >
                        <RefreshCw class="h-4 w-4" :class="{ 'animate-spin': isRefreshing }" />
                        تحديث الحالة
                    </button>
                </div>
            </div>
        </main>
    </div>
</template>
