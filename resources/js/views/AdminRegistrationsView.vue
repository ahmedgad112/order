<script setup>
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { RouterLink } from 'vue-router';
import {
    ArrowRight,
    CheckCircle2,
    ClipboardList,
    LogOut,
    RefreshCw,
    Search,
    UserCheck,
} from 'lucide-vue-next';
import { useAuthStore } from '../stores/authStore';
import { useQueueStore } from '../stores/queueStore';
import { useRouter } from 'vue-router';
import ChangePasswordButton from '../components/ChangePasswordButton.vue';

const authStore = useAuthStore();
const queueStore = useQueueStore();
const router = useRouter();

const search = ref('');
const statusFilter = ref('all');
const loading = ref(false);
const actionId = ref(null);
const feedback = ref('');

const statusOptions = [
    { value: 'all', label: 'الكل' },
    { value: 'waiting', label: 'في الانتظار' },
    { value: 'serving', label: 'قيد الخدمة' },
    { value: 'completed', label: 'دخل' },
    { value: 'cancelled', label: 'ملغى' },
    { value: 'absent', label: 'مش موجود' },
];

const statusBadgeClass = {
    waiting: 'bg-amber-100 text-amber-800',
    serving: 'bg-blue-100 text-blue-800',
    completed: 'bg-green-100 text-green-800',
    cancelled: 'bg-red-100 text-red-800',
    absent: 'bg-orange-100 text-orange-800',
};

const filteredCount = computed(() => queueStore.registrations.length);

function formatTime(iso) {
    if (!iso) return '—';
    return new Date(iso).toLocaleTimeString('ar-EG', { hour: '2-digit', minute: '2-digit' });
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
        await queueStore.fetchRegistrations({
            status: statusFilter.value,
            search: search.value.trim() || undefined,
        });
    } finally {
        if (!silent) {
            loading.value = false;
        }
    }
}

async function handleMarkEntered(ticket) {
    if (!confirm(`تأكيد تسجيل دخول "${ticket.full_name}" (تذكرة ${ticket.ticket_number})؟`)) {
        return;
    }

    actionId.value = ticket.id;
    feedback.value = '';
    try {
        const result = await queueStore.markTicketEntered(ticket.id);
        feedback.value = result.message;
    } catch (err) {
        feedback.value = err.response?.data?.message ?? 'تعذر تسجيل الدخول.';
    } finally {
        actionId.value = null;
    }
}

function canMarkEntered(ticket) {
    return ['waiting', 'serving'].includes(ticket.status);
}

async function logout() {
    await authStore.logout();
    await router.push('/login');
}

function onQueueUpdate() {
    loadTickets();
}

let searchTimer = null;

watch(statusFilter, () => loadTickets());

watch(search, () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => loadTickets(), 400);
});

let unsubscribeEcho = null;
let stopAutoRefresh = null;

onMounted(async () => {
    await loadTickets();

    unsubscribeEcho = queueStore.subscribeEcho({
        TicketIssued: onQueueUpdate,
        TicketCalled: onQueueUpdate,
        TicketCompleted: onQueueUpdate,
        TicketAbsent: onQueueUpdate,
        TicketRestored: onQueueUpdate,
        QueueDayReset: onQueueUpdate,
    });

    stopAutoRefresh = queueStore.startAutoRefresh(() => loadTickets(true));
});

onUnmounted(() => {
    unsubscribeEcho?.();
    stopAutoRefresh?.();
    clearTimeout(searchTimer);
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
                        <h1 class="flex items-center gap-2 text-2xl font-bold text-slate-900">
                            <ClipboardList class="h-7 w-7 text-indigo-600" />
                            سجل التسجيلات
                        </h1>
                        <p class="text-sm text-slate-500">كل الأشخاص المسجلين اليوم — وتسجيل الدخول أمامهم</p>
                    </div>
                </div>
                <div class="flex flex-wrap gap-2">
                    <RouterLink
                        to="/admin"
                        class="flex items-center gap-2 rounded-xl border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                    >
                        لوحة الإدارة
                        <ArrowRight class="h-4 w-4" />
                    </RouterLink>
                    <ChangePasswordButton />
                    <button
                        class="flex items-center gap-2 rounded-xl border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                        @click="logout"
                    >
                        <LogOut class="h-4 w-4" />
                        خروج
                    </button>
                </div>
            </div>
        </header>

        <main class="mx-auto max-w-7xl space-y-5 px-4 py-6 sm:px-6">
            <section class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
                <div class="rounded-2xl bg-white p-4 shadow-sm">
                    <p class="text-xs text-slate-500">الإجمالي</p>
                    <p class="text-2xl font-black text-slate-800">{{ queueStore.registrationStats.total }}</p>
                </div>
                <div class="rounded-2xl bg-amber-50 p-4 shadow-sm">
                    <p class="text-xs text-amber-700">انتظار</p>
                    <p class="text-2xl font-black text-amber-600">{{ queueStore.registrationStats.waiting }}</p>
                </div>
                <div class="rounded-2xl bg-blue-50 p-4 shadow-sm">
                    <p class="text-xs text-blue-700">قيد الخدمة</p>
                    <p class="text-2xl font-black text-blue-600">{{ queueStore.registrationStats.serving }}</p>
                </div>
                <div class="rounded-2xl bg-green-50 p-4 shadow-sm">
                    <p class="text-xs text-green-700">دخل</p>
                    <p class="text-2xl font-black text-green-600">{{ queueStore.registrationStats.completed }}</p>
                </div>
                <div class="rounded-2xl bg-red-50 p-4 shadow-sm">
                    <p class="text-xs text-red-700">ملغى</p>
                    <p class="text-2xl font-black text-red-600">{{ queueStore.registrationStats.cancelled }}</p>
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

                <p v-if="feedback" class="mb-4 rounded-xl bg-green-50 px-4 py-2 text-sm font-semibold text-green-700">
                    {{ feedback }}
                </p>

                <p class="mb-3 text-sm text-slate-500">عرض {{ filteredCount }} تسجيل</p>

                <!-- Mobile cards -->
                <div class="space-y-3 md:hidden">
                    <article
                        v-for="ticket in queueStore.registrations"
                        :key="ticket.id"
                        class="rounded-2xl border border-slate-100 bg-slate-50/60 p-4"
                        :class="{ 'border-green-200 bg-green-50/40': ticket.status === 'completed' }"
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
                                {{ ticket.status_label }}
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
                                <dt class="text-slate-500">طلب دخول</dt>
                                <dd class="font-semibold" :class="ticket.has_entered ? 'text-blue-700' : 'text-slate-400'">
                                    {{ ticket.has_entered ? 'تم' : '—' }}
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
                                <dd class="text-slate-700">{{ formatTime(ticket.created_at) }}</dd>
                            </div>
                        </dl>
                        <div class="mt-4 border-t border-slate-200/80 pt-3">
                            <button
                                v-if="canMarkEntered(ticket)"
                                class="flex w-full items-center justify-center gap-1.5 rounded-xl bg-green-600 px-4 py-2.5 text-sm font-bold text-white hover:bg-green-700 disabled:opacity-60"
                                :disabled="actionId === ticket.id"
                                @click="handleMarkEntered(ticket)"
                            >
                                <UserCheck class="h-4 w-4" />
                                {{ actionId === ticket.id ? 'جاري...' : 'دخل' }}
                            </button>
                            <p
                                v-else-if="ticket.status === 'completed'"
                                class="flex items-center justify-center gap-1 text-xs font-semibold text-green-700"
                            >
                                <CheckCircle2 class="h-4 w-4" />
                                تم
                            </p>
                            <p v-else class="text-center text-xs text-slate-400">—</p>
                        </div>
                    </article>
                    <p
                        v-if="!queueStore.registrations.length"
                        class="py-12 text-center text-slate-400"
                    >
                        لا توجد تسجيلات مطابقة
                    </p>
                </div>

                <!-- Desktop table -->
                <div class="hidden overflow-x-auto md:block">
                    <table class="w-full text-right text-sm">
                        <thead>
                            <tr class="border-b border-slate-100 text-slate-500">
                                <th class="px-3 py-3">#</th>
                                <th class="px-3 py-3">الاسم</th>
                                <th class="px-3 py-3">الرقم القومي</th>
                                <th class="px-3 py-3">رقم الطلب</th>
                                <th class="px-3 py-3">الحالة</th>
                                <th class="px-3 py-3">طلب دخول</th>
                                <th class="px-3 py-3">تم تسليم الملف</th>
                                <th class="px-3 py-3">موظف الشباك</th>
                                <th class="px-3 py-3">وقت التسجيل</th>
                                <th class="px-3 py-3">إجراء</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="ticket in queueStore.registrations"
                                :key="ticket.id"
                                class="border-b border-slate-50 transition hover:bg-slate-50/80"
                                :class="{ 'bg-green-50/50': ticket.status === 'completed' }"
                            >
                                <td class="px-3 py-3 text-lg font-black text-indigo-600">
                                    {{ ticket.ticket_number }}
                                </td>
                                <td class="px-3 py-3 font-semibold text-slate-800">{{ ticket.full_name }}</td>
                                <td class="px-3 py-3 font-mono text-slate-600">{{ ticket.national_id }}</td>
                                <td class="px-3 py-3 text-slate-600">{{ ticket.order_number }}</td>
                                <td class="px-3 py-3">
                                    <span
                                        class="rounded-full px-2.5 py-1 text-xs font-bold"
                                        :class="statusBadgeClass[ticket.status]"
                                    >
                                        {{ ticket.status_label }}
                                    </span>
                                </td>
                                <td class="px-3 py-3">
                                    <span
                                        v-if="ticket.has_entered"
                                        class="inline-flex items-center gap-1 text-xs font-semibold text-blue-700"
                                    >
                                        <CheckCircle2 class="h-4 w-4" />
                                        تم
                                    </span>
                                    <span v-else class="text-xs text-slate-400">—</span>
                                </td>
                                <td class="px-3 py-3">
                                    <span
                                        v-if="ticket.file_delivered"
                                        class="inline-flex items-center gap-1 text-xs font-semibold text-green-700"
                                    >
                                        <CheckCircle2 class="h-4 w-4" />
                                        تم
                                    </span>
                                    <span v-else class="text-xs text-slate-400">—</span>
                                </td>
                                <td class="px-3 py-3">{{ tellerLabel(ticket) }}</td>
                                <td class="px-3 py-3 text-slate-500">{{ formatTime(ticket.created_at) }}</td>
                                <td class="px-3 py-3">
                                    <button
                                        v-if="canMarkEntered(ticket)"
                                        class="flex items-center gap-1.5 rounded-xl bg-green-600 px-4 py-2 text-sm font-bold text-white hover:bg-green-700 disabled:opacity-60"
                                        :disabled="actionId === ticket.id"
                                        @click="handleMarkEntered(ticket)"
                                    >
                                        <UserCheck class="h-4 w-4" />
                                        {{ actionId === ticket.id ? 'جاري...' : 'دخل' }}
                                    </button>
                                    <span
                                        v-else-if="ticket.status === 'completed'"
                                        class="inline-flex items-center gap-1 text-xs font-semibold text-green-700"
                                    >
                                        <CheckCircle2 class="h-4 w-4" />
                                        تم
                                    </span>
                                    <span v-else class="text-xs text-slate-400">—</span>
                                </td>
                            </tr>
                            <tr v-if="!queueStore.registrations.length">
                                <td colspan="10" class="py-16 text-center text-slate-400">
                                    لا توجد تسجيلات مطابقة
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </main>
    </div>
</template>
