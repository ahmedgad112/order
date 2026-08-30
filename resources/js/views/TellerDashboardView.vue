<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import {
    BellRing,
    CheckCircle2,
    Lock,
    LogOut,
    PhoneCall,
    SkipForward,
    Ticket,
    XCircle,
} from 'lucide-vue-next';
import { useAuthStore } from '../stores/authStore';
import { useQueueStore } from '../stores/queueStore';

const authStore = useAuthStore();
const queueStore = useQueueStore();
const router = useRouter();
const actionMessage = ref('');
const actionError = ref('');

const statusLabel = computed(() => ({
    waiting: 'في الانتظار',
    serving: 'قيد الخدمة',
    completed: 'مكتمل',
    cancelled: 'ملغى',
}));

async function refresh() {
    await Promise.all([
        queueStore.fetchTellerStatus(),
        queueStore.fetchCurrentTicket(),
    ]);
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

async function handleRecall() {
    if (!queueStore.currentTicket) {
        return;
    }
    actionError.value = '';
    try {
        await queueStore.recallTicket(queueStore.currentTicket.id);
        actionMessage.value = 'تم إعادة نداء التذكرة.';
    } catch (err) {
        actionError.value = err.response?.data?.message ?? 'تعذر إعادة النداء.';
    }
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

onMounted(async () => {
    queueStore.bindEcho();
    await refresh();
    window.addEventListener('keydown', onKeydown);
});

onUnmounted(() => {
    window.removeEventListener('keydown', onKeydown);
    queueStore.unbindEcho();
});
</script>

<template>
    <div class="min-h-screen bg-slate-100">
        <header class="border-b border-slate-200 bg-white">
            <div class="mx-auto flex max-w-7xl items-center justify-between px-6 py-4">
                <div>
                    <h1 class="text-2xl font-bold text-slate-900">لوحة الموظف</h1>
                    <p class="text-sm text-slate-500">
                        {{ authStore.user?.name }} — {{ authStore.user?.counter_name ?? 'بدون شباك' }}
                    </p>
                </div>
                <button
                    class="flex items-center gap-2 rounded-xl border border-slate-200 px-4 py-2 text-slate-700 hover:bg-slate-50"
                    @click="logout"
                >
                    <LogOut class="h-4 w-4" />
                    خروج
                </button>
            </div>
        </header>

        <main class="mx-auto grid max-w-7xl gap-6 px-6 py-6 lg:grid-cols-3">
            <div
                v-if="!queueStore.isSystemOpen"
                class="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-red-800 lg:col-span-3"
            >
                <div class="flex items-center gap-2 font-semibold">
                    <Lock class="h-4 w-4" />
                    النظام مغلق — لا يمكن نداء تذاكر جديدة. يمكنك إكمال التذكرة الحالية فقط.
                </div>
            </div>

            <section class="space-y-4 lg:col-span-2">
                <div class="rounded-3xl bg-white p-6 shadow-sm">
                    <div class="mb-4 flex items-center justify-between">
                        <h2 class="text-lg font-bold text-slate-800">التذكرة الحالية</h2>
                        <span class="rounded-full bg-blue-100 px-3 py-1 text-xs font-semibold text-blue-700">
                            مسافة: نداء | Enter: إكمال | Esc: إلغاء
                        </span>
                    </div>

                    <div v-if="queueStore.currentTicket" class="rounded-2xl border border-blue-100 bg-blue-50 p-6">
                        <div class="flex items-start justify-between">
                            <div>
                                <p class="text-sm text-blue-600">رقم التذكرة</p>
                                <p class="text-6xl font-black text-blue-700">{{ queueStore.currentTicket.ticket_number }}</p>
                            </div>
                            <span class="rounded-full bg-amber-100 px-3 py-1 text-sm font-semibold text-amber-700">
                                {{ statusLabel[queueStore.currentTicket.status] ?? queueStore.currentTicket.status }}
                            </span>
                        </div>
                        <p class="mt-4 text-2xl font-bold text-slate-800">{{ queueStore.currentTicket.full_name }}</p>
                        <p class="mt-2 text-sm text-slate-500">رقم الطلب: {{ queueStore.currentTicket.order_number }}</p>
                    </div>
                    <div v-else class="rounded-2xl border border-dashed border-slate-200 py-16 text-center text-slate-500">
                        لا توجد تذكرة قيد الخدمة — اضغط "نداء التالي"
                    </div>

                    <p v-if="actionMessage" class="mt-4 text-sm text-green-600">{{ actionMessage }}</p>
                    <p v-if="actionError" class="mt-4 text-sm text-red-600">{{ actionError }}</p>

                    <div class="mt-6 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                        <button
                            class="flex items-center justify-center gap-2 rounded-2xl bg-blue-600 px-4 py-4 font-bold text-white hover:bg-blue-700 disabled:opacity-50"
                            :disabled="!queueStore.isSystemOpen"
                            @click="handleCallNext"
                        >
                            <PhoneCall class="h-5 w-5" />
                            نداء التالي
                        </button>
                        <button
                            :disabled="!queueStore.hasCurrentTicket"
                            class="flex items-center justify-center gap-2 rounded-2xl bg-green-600 px-4 py-4 font-bold text-white hover:bg-green-700 disabled:opacity-50"
                            @click="handleComplete"
                        >
                            <CheckCircle2 class="h-5 w-5" />
                            إكمال
                        </button>
                        <button
                            :disabled="!queueStore.hasCurrentTicket"
                            class="flex items-center justify-center gap-2 rounded-2xl bg-amber-500 px-4 py-4 font-bold text-white hover:bg-amber-600 disabled:opacity-50"
                            @click="handleRecall"
                        >
                            <BellRing class="h-5 w-5" />
                            إعادة نداء
                        </button>
                        <button
                            :disabled="!queueStore.hasCurrentTicket"
                            class="flex items-center justify-center gap-2 rounded-2xl bg-red-600 px-4 py-4 font-bold text-white hover:bg-red-700 disabled:opacity-50"
                            @click="handleCancel"
                        >
                            <XCircle class="h-5 w-5" />
                            إلغاء
                        </button>
                    </div>
                </div>
            </section>

            <aside class="space-y-4">
                <div class="grid grid-cols-3 gap-3">
                    <div class="rounded-2xl bg-white p-4 text-center shadow-sm">
                        <p class="text-xs text-slate-500">انتظار</p>
                        <p class="text-2xl font-bold text-amber-600">{{ queueStore.stats.waiting }}</p>
                    </div>
                    <div class="rounded-2xl bg-white p-4 text-center shadow-sm">
                        <p class="text-xs text-slate-500">خدمة</p>
                        <p class="text-2xl font-bold text-blue-600">{{ queueStore.stats.serving }}</p>
                    </div>
                    <div class="rounded-2xl bg-white p-4 text-center shadow-sm">
                        <p class="text-xs text-slate-500">مكتمل</p>
                        <p class="text-2xl font-bold text-green-600">{{ queueStore.stats.completed }}</p>
                    </div>
                </div>

                <div class="rounded-3xl bg-white p-5 shadow-sm">
                    <div class="mb-4 flex items-center gap-2">
                        <Ticket class="h-5 w-5 text-slate-500" />
                        <h3 class="font-bold text-slate-800">قائمة الانتظار</h3>
                    </div>
                    <ul class="max-h-96 space-y-2 overflow-y-auto">
                        <li
                            v-for="ticket in queueStore.waiting"
                            :key="ticket.id"
                            class="flex items-center justify-between rounded-xl bg-slate-50 px-3 py-2"
                        >
                            <span class="font-bold text-slate-800">{{ ticket.ticket_number }}</span>
                            <span class="text-sm text-slate-500">{{ ticket.masked_name }}</span>
                        </li>
                        <li v-if="!queueStore.waiting.length" class="py-8 text-center text-sm text-slate-400">
                            <SkipForward class="mx-auto mb-2 h-6 w-6" />
                            القائمة فارغة
                        </li>
                    </ul>
                </div>
            </aside>
        </main>
    </div>
</template>
