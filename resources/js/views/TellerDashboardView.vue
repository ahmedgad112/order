<script setup>
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { useRouter } from 'vue-router';
import {
    CheckCircle2,
    ClipboardList,
    FolderCheck,
    Lock,
    LogOut,
    PhoneCall,
    RefreshCw,
    RotateCcw,
    Search,
    UserCheck,
    UserX,
} from 'lucide-vue-next';
import { useAuthStore } from '../stores/authStore';
import { useQueueStore } from '../stores/queueStore';

const authStore = useAuthStore();
const queueStore = useQueueStore();
const router = useRouter();
const actionMessage = ref('');
const actionError = ref('');
const restoringId = ref(null);
const enteredId = ref(null);
const deliveredId = ref(null);
const absentId = ref(null);
const search = ref('');
const statusFilter = ref('all');
const loading = ref(false);

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

const statusOptions = [
    { value: 'all', label: 'الكل' },
    { value: 'waiting', label: 'في الانتظار' },
    { value: 'serving', label: 'قيد الخدمة' },
    { value: 'completed', label: 'مكتمل' },
    { value: 'absent', label: 'مش موجود' },
    { value: 'cancelled', label: 'ملغى' },
];

const filteredCount = computed(() => queueStore.tellerTickets.length);

const servingNow = computed(() => {
    const serving = (queueStore.serving ?? []).filter((ticket) => ticket.status === 'serving');
    const detailed = queueStore.tellerTickets.filter((ticket) => ticket.status === 'serving');
    const byId = Object.fromEntries(detailed.map((ticket) => [ticket.id, ticket]));

    if (!serving.length) {
        return detailed;
    }

    return serving.map((ticket) => byId[ticket.id] ?? ticket);
});

async function loadTickets(silent = false) {
    if (!silent) {
        loading.value = true;
    }
    try {
        await queueStore.fetchTellerTickets({
            status: statusFilter.value,
            search: search.value.trim() || undefined,
        });
    } finally {
        if (!silent) {
            loading.value = false;
        }
    }
}

async function refresh() {
    await Promise.all([
        loadTickets(),
        queueStore.fetchTellerStatus(),
        queueStore.fetchCurrentTicket(),
        queueStore.fetchAbsentTickets(),
    ]);
}

function canMarkEntered(ticket) {
    return ['waiting', 'serving'].includes(ticket.status) && !ticket.has_entered;
}

function canMarkFileDelivered(ticket) {
    return Boolean(ticket.has_entered) && !ticket.file_delivered && ticket.status !== 'cancelled' && ticket.status !== 'absent';
}

function canMarkAbsent(ticket) {
    return ['waiting', 'serving'].includes(ticket.status) && !ticket.file_delivered;
}

async function handleMarkEntered(ticket) {
    enteredId.value = ticket.id;
    actionError.value = '';
    try {
        const result = await queueStore.markTellerEntered(ticket.id);
        actionMessage.value = result.message;
        await loadTickets();
    } catch (err) {
        actionError.value = err.response?.data?.message
            ?? err.response?.data?.errors?.ticket?.[0]
            ?? 'تعذر تسجيل طلب الدخول.';
    } finally {
        enteredId.value = null;
    }
}

async function handleMarkFileDelivered(ticket) {
    deliveredId.value = ticket.id;
    actionError.value = '';
    try {
        const result = await queueStore.markFileDelivered(ticket.id);
        actionMessage.value = result.message;
        await loadTickets();
    } catch (err) {
        actionError.value = err.response?.data?.message
            ?? err.response?.data?.errors?.ticket?.[0]
            ?? 'تعذر تسجيل تسليم الملف.';
    } finally {
        deliveredId.value = null;
    }
}

async function handleCallNext() {
    actionError.value = '';
    try {
        await queueStore.callNext();
        actionMessage.value = 'تم نداء التذكرة التالية.';
        await loadTickets();
    } catch (err) {
        actionError.value = err.response?.data?.message
            ?? err.response?.data?.errors?.queue?.[0]
            ?? 'تعذر نداء التذكرة التالية.';
    }
}

async function handleComplete() {
    if (!queueStore.currentTicket) {
        return;
    }
    actionError.value = '';
    try {
        await queueStore.completeTicket(queueStore.currentTicket.id);
        actionMessage.value = 'تم إكمال التذكرة.';
        await loadTickets();
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
        await loadTickets();
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
        await loadTickets();
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
        await loadTickets();
    } catch (err) {
        actionError.value = err.response?.data?.message
            ?? err.response?.data?.errors?.ticket?.[0]
            ?? 'تعذر إرجاع التذكرة.';
    } finally {
        restoringId.value = null;
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

async function logout() {
    await authStore.logout();
    await router.push('/login');
}

let searchTimer = null;
let unsubscribeEcho = null;
let stopAutoRefresh = null;

watch(statusFilter, () => loadTickets());

watch(search, () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => loadTickets(), 400);
});

onMounted(async () => {
    await refresh();
    window.addEventListener('keydown', onKeydown);

    unsubscribeEcho = queueStore.subscribeEcho({
        TicketIssued: () => loadTickets(true),
        TicketCalled: () => loadTickets(true),
        TicketCompleted: () => loadTickets(true),
        TicketAbsent: () => loadTickets(true),
        TicketRestored: () => loadTickets(true),
        QueueDayReset: () => loadTickets(true),
    });

    stopAutoRefresh = queueStore.startAutoRefresh(async () => {
        await Promise.all([
            loadTickets(true),
            queueStore.fetchTellerStatus(),
            queueStore.fetchCurrentTicket(),
            queueStore.fetchAbsentTickets(),
        ]);
    });
});

onUnmounted(() => {
    window.removeEventListener('keydown', onKeydown);
    clearTimeout(searchTimer);
    unsubscribeEcho?.();
    stopAutoRefresh?.();
});
</script>

<template>
    <div class="min-h-screen bg-slate-100">
        <header class="border-b border-slate-200 bg-white">
            <div class="mx-auto flex max-w-7xl flex-col gap-4 px-4 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                <div class="flex items-center gap-3">
                    <img
                        :src="'/logo.webp'"
                        alt="جامعة برج العرب التكنولوجية"
                        class="h-12 w-auto object-contain"
                    />
                    <div>
                        <h1 class="text-2xl font-bold text-slate-900">لوحة الموظف</h1>
                        <p class="text-sm text-slate-500">
                            {{ authStore.user?.name }} — {{ authStore.user?.counter_name ?? 'بدون شباك' }}
                        </p>
                    </div>
                </div>
                <button
                    class="flex w-fit items-center gap-2 rounded-xl border border-slate-200 px-4 py-2 text-slate-700 hover:bg-slate-50"
                    @click="logout"
                >
                    <LogOut class="h-4 w-4" />
                    خروج
                </button>
            </div>
        </header>

        <main class="mx-auto max-w-7xl space-y-6 px-4 py-6 sm:px-6">
            <div
                v-if="!queueStore.isSystemOpen"
                class="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-red-800"
            >
                <div class="flex items-center gap-2 font-semibold">
                    <Lock class="h-4 w-4" />
                    النظام مغلق — يمكن تسليم الملفات للتذاكر اللي دخلت بالفعل فقط.
                </div>
            </div>

            <section class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
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
                <div class="rounded-2xl bg-green-50 p-4 shadow-sm">
                    <p class="text-xs text-green-700">تم تسليم الملف</p>
                    <p class="text-2xl font-black text-green-600">{{ queueStore.tellerTicketStats.file_delivered }}</p>
                </div>
            </section>

            <section class="rounded-3xl border border-blue-100 bg-white p-5 shadow-sm">
                <div class="mb-4 flex items-center justify-between gap-3">
                    <h2 class="text-lg font-bold text-slate-800">قيد الخدمة الآن</h2>
                    <span class="rounded-full bg-blue-100 px-2.5 py-0.5 text-xs font-semibold text-blue-700">
                        {{ servingNow.length }}
                    </span>
                </div>

                <div v-if="!servingNow.length" class="rounded-2xl border border-dashed border-slate-200 py-10 text-center text-sm text-slate-400">
                    لا يوجد أحد قيد الخدمة حالياً
                </div>

                <div v-else class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                    <article
                        v-for="ticket in servingNow"
                        :key="ticket.id"
                        class="rounded-2xl border border-blue-100 bg-blue-50 p-4"
                    >
                        <p class="text-xs font-semibold text-blue-600">تذكرة {{ ticket.ticket_number }}</p>
                        <p class="mt-1 text-xl font-black text-slate-900">{{ ticket.full_name || ticket.masked_name }}</p>
                        <p class="mt-2 text-sm text-slate-600">{{ tellerLabel(ticket) }}</p>
                    </article>
                </div>
            </section>

            <section class="rounded-3xl bg-white p-5 shadow-sm">
                <div class="mb-4 flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <h2 class="flex items-center gap-2 text-lg font-bold text-slate-800">
                            <ClipboardList class="h-5 w-5 text-indigo-600" />
                            سجل الطلبات
                        </h2>
                        <p class="mt-1 text-sm text-slate-500">كل الطلبات اليوم — سجّل الدخول وتسليم الملف من الجدول</p>
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
                </div>

                <p v-if="actionMessage" class="mb-4 text-sm text-green-600">{{ actionMessage }}</p>
                <p v-if="actionError" class="mb-4 text-sm text-red-600">{{ actionError }}</p>

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
                            v-for="opt in statusOptions"
                            :key="opt.value"
                            type="button"
                            class="rounded-xl px-3 py-2 text-sm font-semibold transition"
                            :class="statusFilter === opt.value
                                ? 'bg-indigo-600 text-white'
                                : 'bg-slate-100 text-slate-600 hover:bg-slate-200'"
                            @click="statusFilter = opt.value"
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

                <div class="space-y-3 md:hidden">
                    <article
                        v-for="ticket in queueStore.tellerTickets"
                        :key="ticket.id"
                        class="rounded-2xl border border-slate-100 bg-slate-50/60 p-4"
                        :class="{
                            'border-green-200 bg-green-50/40': ticket.file_delivered,
                            'border-blue-200 bg-blue-50/40': ticket.has_entered && !ticket.file_delivered,
                            'border-orange-200 bg-orange-50/40': ticket.status === 'absent',
                        }"
                    >
                        <div class="mb-3 flex items-start justify-between gap-3">
                            <div>
                                <p class="text-2xl font-black text-indigo-600">{{ ticket.ticket_number }}</p>
                                <p class="mt-1 font-semibold text-slate-800">{{ ticket.full_name }}</p>
                            </div>
                            <span
                                class="shrink-0 rounded-full px-2.5 py-1 text-xs font-bold"
                                :class="statusBadgeClass[ticket.status]"
                            >
                                {{ statusLabel[ticket.status] ?? ticket.status }}
                            </span>
                        </div>
                        <dl class="space-y-2 text-sm">
                            <div class="flex justify-between gap-3">
                                <dt class="text-slate-500">الرقم القومي</dt>
                                <dd class="font-mono text-slate-700">{{ ticket.national_id }}</dd>
                            </div>
                            <div class="flex justify-between gap-3">
                                <dt class="text-slate-500">رقم الطلب</dt>
                                <dd class="text-slate-700">{{ ticket.order_number }}</dd>
                            </div>
                            <div class="flex justify-between gap-3">
                                <dt class="text-slate-500">موظف الشباك</dt>
                                <dd class="text-slate-700">{{ tellerLabel(ticket) }}</dd>
                            </div>
                        </dl>
                        <div class="mt-4 grid grid-cols-2 gap-2 border-t border-slate-200/80 pt-3">
                            <button
                                v-if="canMarkEntered(ticket)"
                                class="flex items-center justify-center gap-1.5 rounded-xl bg-blue-600 px-3 py-2.5 text-sm font-bold text-white hover:bg-blue-700 disabled:opacity-60"
                                :disabled="enteredId === ticket.id || !queueStore.isSystemOpen"
                                @click="handleMarkEntered(ticket)"
                            >
                                <UserCheck class="h-4 w-4" />
                                {{ enteredId === ticket.id ? 'جاري...' : 'طلب دخول' }}
                            </button>
                            <p
                                v-else-if="ticket.has_entered"
                                class="flex items-center justify-center gap-1 text-xs font-semibold text-blue-700"
                            >
                                <CheckCircle2 class="h-4 w-4" />
                                تم الدخول
                            </p>
                            <p v-else class="text-center text-xs text-slate-400">—</p>

                            <button
                                v-if="canMarkFileDelivered(ticket)"
                                class="flex items-center justify-center gap-1.5 rounded-xl bg-green-600 px-3 py-2.5 text-sm font-bold text-white hover:bg-green-700 disabled:opacity-60"
                                :disabled="deliveredId === ticket.id"
                                @click="handleMarkFileDelivered(ticket)"
                            >
                                <FolderCheck class="h-4 w-4" />
                                {{ deliveredId === ticket.id ? 'جاري...' : 'تسليم الملف' }}
                            </button>
                            <p
                                v-else-if="ticket.file_delivered"
                                class="flex items-center justify-center gap-1 text-xs font-semibold text-green-700"
                            >
                                <CheckCircle2 class="h-4 w-4" />
                                تم التسليم
                            </p>
                            <p v-else class="text-center text-xs text-slate-400">—</p>

                            <button
                                v-if="canMarkAbsent(ticket)"
                                class="col-span-2 flex items-center justify-center gap-1.5 rounded-xl bg-orange-600 px-3 py-2.5 text-sm font-bold text-white hover:bg-orange-700 disabled:opacity-60"
                                :disabled="absentId === ticket.id"
                                @click="handleMarkAbsent(ticket)"
                            >
                                <UserX class="h-4 w-4" />
                                {{ absentId === ticket.id ? 'جاري...' : 'مش موجود' }}
                            </button>
                            <button
                                v-else-if="ticket.status === 'absent'"
                                class="col-span-2 flex items-center justify-center gap-1.5 rounded-xl bg-emerald-600 px-3 py-2.5 text-sm font-bold text-white hover:bg-emerald-700 disabled:opacity-60"
                                :disabled="restoringId === ticket.id"
                                @click="handleRestore(ticket)"
                            >
                                <RotateCcw class="h-4 w-4" />
                                {{ restoringId === ticket.id ? 'جاري الإرجاع...' : 'إرجاع للطابور' }}
                            </button>
                        </div>
                    </article>
                    <p
                        v-if="!queueStore.tellerTickets.length"
                        class="py-12 text-center text-slate-400"
                    >
                        لا توجد طلبات مطابقة
                    </p>
                </div>

                <div class="hidden overflow-x-auto md:block">
                    <table class="w-full min-w-[1080px] text-right text-sm">
                        <thead>
                            <tr class="border-b border-slate-100 text-slate-500">
                                <th class="px-3 py-3">#</th>
                                <th class="px-3 py-3">الاسم</th>
                                <th class="px-3 py-3">الرقم القومي</th>
                                <th class="px-3 py-3">رقم الطلب</th>
                                <th class="px-3 py-3">موظف الشباك</th>
                                <th class="px-3 py-3">الحالة</th>
                                <th class="px-3 py-3">طلب دخول</th>
                                <th class="px-3 py-3">تم تسليم الملف</th>
                                <th class="px-3 py-3">مش موجود</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="ticket in queueStore.tellerTickets"
                                :key="ticket.id"
                                class="border-b border-slate-50 transition hover:bg-slate-50/80"
                                :class="{
                                    'bg-green-50/50': ticket.file_delivered,
                                    'bg-blue-50/40': ticket.has_entered && !ticket.file_delivered,
                                    'bg-orange-50/50': ticket.status === 'absent',
                                }"
                            >
                                <td class="px-3 py-3 text-lg font-black text-indigo-600">
                                    {{ ticket.ticket_number }}
                                </td>
                                <td class="px-3 py-3 font-semibold text-slate-800">{{ ticket.full_name }}</td>
                                <td class="px-3 py-3 font-mono text-slate-600">{{ ticket.national_id }}</td>
                                <td class="px-3 py-3 text-slate-600">{{ ticket.order_number }}</td>
                                <td class="px-3 py-3 font-semibold text-slate-700">{{ tellerLabel(ticket) }}</td>
                                <td class="px-3 py-3">
                                    <span
                                        class="rounded-full px-2.5 py-1 text-xs font-bold"
                                        :class="statusBadgeClass[ticket.status]"
                                    >
                                        {{ statusLabel[ticket.status] ?? ticket.status }}
                                    </span>
                                </td>
                                <td class="px-3 py-3">
                                    <button
                                        v-if="canMarkEntered(ticket)"
                                        class="inline-flex items-center gap-1.5 rounded-xl bg-blue-600 px-3 py-2 text-sm font-bold text-white hover:bg-blue-700 disabled:opacity-60"
                                        :disabled="enteredId === ticket.id || !queueStore.isSystemOpen"
                                        @click="handleMarkEntered(ticket)"
                                    >
                                        <UserCheck class="h-4 w-4" />
                                        {{ enteredId === ticket.id ? 'جاري...' : 'طلب دخول' }}
                                    </button>
                                    <span
                                        v-else-if="ticket.has_entered"
                                        class="inline-flex items-center gap-1 text-xs font-semibold text-blue-700"
                                    >
                                        <CheckCircle2 class="h-4 w-4" />
                                        تم الدخول
                                    </span>
                                    <span v-else class="text-xs text-slate-400">—</span>
                                </td>
                                <td class="px-3 py-3">
                                    <button
                                        v-if="canMarkFileDelivered(ticket)"
                                        class="inline-flex items-center gap-1.5 rounded-xl bg-green-600 px-3 py-2 text-sm font-bold text-white hover:bg-green-700 disabled:opacity-60"
                                        :disabled="deliveredId === ticket.id"
                                        @click="handleMarkFileDelivered(ticket)"
                                    >
                                        <FolderCheck class="h-4 w-4" />
                                        {{ deliveredId === ticket.id ? 'جاري...' : 'تسليم الملف' }}
                                    </button>
                                    <span
                                        v-else-if="ticket.file_delivered"
                                        class="inline-flex items-center gap-1 text-xs font-semibold text-green-700"
                                    >
                                        <CheckCircle2 class="h-4 w-4" />
                                        تم التسليم
                                    </span>
                                    <span v-else class="text-xs text-slate-400">—</span>
                                </td>
                                <td class="px-3 py-3">
                                    <button
                                        v-if="canMarkAbsent(ticket)"
                                        class="inline-flex items-center gap-1.5 rounded-xl bg-orange-600 px-3 py-2 text-sm font-bold text-white hover:bg-orange-700 disabled:opacity-60"
                                        :disabled="absentId === ticket.id"
                                        @click="handleMarkAbsent(ticket)"
                                    >
                                        <UserX class="h-4 w-4" />
                                        {{ absentId === ticket.id ? 'جاري...' : 'مش موجود' }}
                                    </button>
                                    <button
                                        v-else-if="ticket.status === 'absent'"
                                        class="inline-flex items-center gap-1.5 rounded-xl bg-emerald-600 px-3 py-2 text-sm font-bold text-white hover:bg-emerald-700 disabled:opacity-60"
                                        :disabled="restoringId === ticket.id"
                                        @click="handleRestore(ticket)"
                                    >
                                        <RotateCcw class="h-4 w-4" />
                                        {{ restoringId === ticket.id ? 'جاري...' : 'إرجاع' }}
                                    </button>
                                    <span v-else class="text-xs text-slate-400">—</span>
                                </td>
                            </tr>
                            <tr v-if="!queueStore.tellerTickets.length">
                                <td colspan="9" class="py-16 text-center text-slate-400">
                                    لا توجد طلبات مطابقة
                                </td>
                            </tr>
                        </tbody>
                    </table>
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

                <div v-else class="overflow-x-auto">
                    <table class="w-full min-w-[640px] text-right text-sm">
                        <thead>
                            <tr class="border-b border-slate-100 text-slate-500">
                                <th class="px-3 py-2">رقم التذكرة</th>
                                <th class="px-3 py-2">الاسم</th>
                                <th class="px-3 py-2">رقم الطلب</th>
                                <th class="px-3 py-2">وقت النداء</th>
                                <th class="px-3 py-2">موظف الشباك</th>
                                <th class="px-3 py-2">إجراء</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="ticket in queueStore.absentTickets"
                                :key="ticket.id"
                                class="border-b border-slate-50"
                            >
                                <td class="px-3 py-3 text-lg font-bold text-orange-600">{{ ticket.ticket_number }}</td>
                                <td class="px-3 py-3 font-semibold text-slate-800">{{ ticket.full_name }}</td>
                                <td class="px-3 py-3 text-slate-600">{{ ticket.order_number }}</td>
                                <td class="px-3 py-3 text-slate-600">{{ formatTime(ticket.called_at) }}</td>
                                <td class="px-3 py-3 text-slate-600">{{ tellerLabel(ticket) }}</td>
                                <td class="px-3 py-3">
                                    <button
                                        class="inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-3 py-2 text-xs font-bold text-white hover:bg-emerald-700 disabled:opacity-60"
                                        :disabled="restoringId === ticket.id"
                                        @click="handleRestore(ticket)"
                                    >
                                        <RotateCcw class="h-4 w-4" />
                                        {{ restoringId === ticket.id ? 'جاري الإرجاع...' : 'إرجاع للطابور' }}
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </main>
    </div>
</template>
