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
];

const statusBadgeClass = {
    waiting: 'bg-amber-100 text-amber-800',
    serving: 'bg-blue-100 text-blue-800',
    completed: 'bg-green-100 text-green-800',
    cancelled: 'bg-red-100 text-red-800',
};

const filteredCount = computed(() => queueStore.registrations.length);

function formatTime(iso) {
    if (!iso) return '—';
    return new Date(iso).toLocaleTimeString('ar-EG', { hour: '2-digit', minute: '2-digit' });
}

async function loadTickets() {
    loading.value = true;
    try {
        await queueStore.fetchRegistrations({
            status: statusFilter.value,
            search: search.value.trim() || undefined,
        });
    } finally {
        loading.value = false;
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

onMounted(async () => {
    queueStore.bindEcho();
    await loadTickets();

    if (window.Echo) {
        window.Echo.channel('queue-channel')
            .listen('.TicketIssued', onQueueUpdate)
            .listen('.TicketCalled', onQueueUpdate)
            .listen('.TicketCompleted', onQueueUpdate)
            .listen('.QueueDayReset', onQueueUpdate);
    }
});

onUnmounted(() => {
    queueStore.unbindEcho();
    clearTimeout(searchTimer);
});
</script>

<template>
    <div class="min-h-screen bg-slate-100">
        <header class="border-b border-slate-200 bg-white">
            <div class="mx-auto flex max-w-7xl flex-col gap-4 px-6 py-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="flex items-center gap-2 text-2xl font-bold text-slate-900">
                        <ClipboardList class="h-7 w-7 text-indigo-600" />
                        سجل التسجيلات
                    </h1>
                    <p class="text-sm text-slate-500">كل الأشخاص المسجلين اليوم — وتسجيل الدخول أمامهم</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <RouterLink
                        to="/admin"
                        class="flex items-center gap-2 rounded-xl border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                    >
                        لوحة الإدارة
                        <ArrowRight class="h-4 w-4" />
                    </RouterLink>
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

        <main class="mx-auto max-w-7xl space-y-5 px-6 py-6">
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

                <div class="overflow-x-auto">
                    <table class="w-full min-w-[900px] text-right text-sm">
                        <thead>
                            <tr class="border-b border-slate-100 text-slate-500">
                                <th class="px-3 py-3">#</th>
                                <th class="px-3 py-3">الاسم</th>
                                <th class="px-3 py-3">الرقم القومي</th>
                                <th class="px-3 py-3">رقم الطلب</th>
                                <th class="px-3 py-3">الحالة</th>
                                <th class="px-3 py-3">الشباك</th>
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
                                <td class="px-3 py-3">{{ ticket.counter_name ?? '—' }}</td>
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
                                <td colspan="8" class="py-16 text-center text-slate-400">
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
