<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { useRouter, RouterLink } from 'vue-router';
import {
    BarChart3,
    CheckCircle,
    ClipboardList,
    Clock3,
    Lock,
    LogOut,
    Power,
    RefreshCcw,
    Timer,
    Unlock,
    Users,
    UserCheck,
} from 'lucide-vue-next';
import { useAuthStore } from '../stores/authStore';
import { useQueueStore } from '../stores/queueStore';
import AdminUserManagement from '../components/AdminUserManagement.vue';
import ChangePasswordButton from '../components/ChangePasswordButton.vue';

const authStore = useAuthStore();
const queueStore = useQueueStore();
const router = useRouter();

const closeMessage = ref('');
const actionLoading = ref(false);
const actionFeedback = ref('');
const actionError = ref('');
const showResetConfirm = ref(false);
let stopAutoRefresh = null;

const avgWaitMinutes = computed(() => {
    const seconds = queueStore.metrics?.avg_wait_seconds ?? 0;
    return Math.round(seconds / 60);
});

const avgHandlingMinutes = computed(() => {
    const seconds = queueStore.metrics?.avg_handling_seconds ?? 0;
    return Math.round(seconds / 60);
});

async function refresh() {
    const tasks = [queueStore.fetchAdminDashboard()];

    if (authStore.canManageUsers) {
        tasks.push(queueStore.fetchUsers());
    }

    await Promise.all(tasks);
}

async function handleCloseSystem() {
    actionLoading.value = true;
    actionError.value = '';
    actionFeedback.value = '';
    try {
        const result = await queueStore.closeSystem(closeMessage.value || null);
        actionFeedback.value = result.message;
        closeMessage.value = '';
    } catch (err) {
        actionError.value = err.response?.data?.message ?? 'تعذر إغلاق النظام.';
    } finally {
        actionLoading.value = false;
    }
}

async function handleOpenSystem() {
    actionLoading.value = true;
    actionError.value = '';
    actionFeedback.value = '';
    try {
        const result = await queueStore.openSystem();
        actionFeedback.value = result.message;
    } catch (err) {
        actionError.value = err.response?.data?.message ?? 'تعذر فتح النظام.';
    } finally {
        actionLoading.value = false;
    }
}

async function handleResetDay() {
    actionLoading.value = true;
    actionError.value = '';
    actionFeedback.value = '';
    try {
        const result = await queueStore.resetDay();
        actionFeedback.value = `${result.message} (تم حذف ${result.deleted_tickets} تذكرة)`;
        showResetConfirm.value = false;
        await refresh();
    } catch (err) {
        actionError.value = err.response?.data?.message ?? 'تعذر تصفير اليوم.';
    } finally {
        actionLoading.value = false;
    }
}

async function logout() {
    await authStore.logout();
    await router.push('/login');
}

onMounted(() => {
    refresh();
    stopAutoRefresh = queueStore.startAutoRefresh(() => refresh(), 8000);
});

onUnmounted(() => {
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
                        <h1 class="text-2xl font-bold text-slate-900">لوحة الإدارة</h1>
                        <p class="text-sm text-slate-500">
                            {{ authStore.user?.role_label ?? 'الإدارة' }}
                            —
                            {{ authStore.canControlSystem
                                ? 'إدارة النظام والحسابات والتقارير اليومية'
                                : 'متابعة التقارير والتشغيل اليومي' }}
                        </p>
                    </div>
                </div>
                <div class="flex flex-wrap gap-2">
                    <RouterLink
                        to="/teller"
                        class="flex items-center gap-2 rounded-xl border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                    >
                        لوحة الموظف
                    </RouterLink>
                    <RouterLink
                        to="/display"
                        target="_blank"
                        class="flex items-center gap-2 rounded-xl border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                    >
                        شاشة العرض
                    </RouterLink>
                    <RouterLink
                        to="/admin/registrations"
                        class="flex items-center gap-2 rounded-xl bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700"
                    >
                        <ClipboardList class="h-4 w-4" />
                        سجل التسجيلات
                    </RouterLink>
                    <ChangePasswordButton />
                    <button
                        class="flex items-center gap-2 rounded-xl border border-slate-200 px-4 py-2 text-slate-700 hover:bg-slate-50"
                        @click="logout"
                    >
                        <LogOut class="h-4 w-4" />
                        خروج
                    </button>
                </div>
            </div>
        </header>

        <main class="mx-auto max-w-7xl space-y-6 px-4 py-6 sm:px-6">
            <section class="rounded-3xl border p-6 shadow-sm" :class="queueStore.isSystemOpen ? 'border-green-200 bg-green-50' : 'border-red-200 bg-red-50'">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <div class="mb-2 flex items-center gap-2">
                            <component :is="queueStore.isSystemOpen ? Unlock : Lock" class="h-5 w-5" />
                            <h2 class="text-lg font-bold text-slate-900">حالة النظام</h2>
                            <span
                                class="rounded-full px-3 py-1 text-xs font-bold"
                                :class="queueStore.isSystemOpen ? 'bg-green-200 text-green-800' : 'bg-red-200 text-red-800'"
                            >
                                {{ queueStore.isSystemOpen ? 'مفتوح' : 'مغلق' }}
                            </span>
                        </div>
                        <p class="text-sm text-slate-600">
                            {{ queueStore.isSystemOpen
                                ? 'النظام يستقبل تذاكر جديدة ويسمح بنداء العملاء.'
                                : (queueStore.system.closed_message || 'النظام مغلق ولا يمكن إصدار تذاكر جديدة.') }}
                        </p>
                        <p v-if="queueStore.system.last_reset_at" class="mt-1 text-xs text-slate-500">
                            آخر تصفير: {{ new Date(queueStore.system.last_reset_at).toLocaleString('ar-EG') }}
                        </p>
                    </div>

                    <div v-if="authStore.canControlSystem" class="flex flex-wrap gap-3">
                        <button
                            v-if="queueStore.isSystemOpen"
                            class="flex items-center gap-2 rounded-xl bg-red-600 px-4 py-2 font-semibold text-white hover:bg-red-700 disabled:opacity-60"
                            :disabled="actionLoading"
                            @click="handleCloseSystem"
                        >
                            <Lock class="h-4 w-4" />
                            إغلاق النظام
                        </button>
                        <button
                            v-else
                            class="flex items-center gap-2 rounded-xl bg-green-600 px-4 py-2 font-semibold text-white hover:bg-green-700 disabled:opacity-60"
                            :disabled="actionLoading"
                            @click="handleOpenSystem"
                        >
                            <Power class="h-4 w-4" />
                            فتح النظام
                        </button>
                        <button
                            class="flex items-center gap-2 rounded-xl border border-amber-300 bg-amber-50 px-4 py-2 font-semibold text-amber-800 hover:bg-amber-100 disabled:opacity-60"
                            :disabled="actionLoading"
                            @click="showResetConfirm = true"
                        >
                            <RefreshCcw class="h-4 w-4" />
                            تصفير اليوم
                        </button>
                    </div>
                    <p v-else class="text-sm font-semibold text-slate-500">
                        فتح النظام وإغلاقه وتصفير اليوم متاح للسوبر أدمن فقط.
                    </p>
                </div>

                <div v-if="authStore.canControlSystem && queueStore.isSystemOpen" class="mt-4">
                    <label class="mb-2 block text-sm font-semibold text-slate-700">رسالة الإغلاق (اختياري)</label>
                    <input
                        v-model="closeMessage"
                        type="text"
                        class="w-full rounded-xl border border-slate-200 px-4 py-2 outline-none focus:border-red-400 focus:ring-4 focus:ring-red-100"
                        placeholder="مثال: انتهى وقت العمل اليوم"
                    />
                </div>

                <p v-if="actionFeedback" class="mt-4 text-sm font-semibold text-green-700">{{ actionFeedback }}</p>
                <p v-if="actionError" class="mt-4 text-sm font-semibold text-red-700">{{ actionError }}</p>
            </section>

            <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <article class="rounded-3xl bg-white p-5 shadow-sm">
                    <div class="mb-3 flex items-center justify-between">
                        <p class="text-sm text-slate-500">إجمالي التذاكر</p>
                        <BarChart3 class="h-5 w-5 text-blue-500" />
                    </div>
                    <p class="text-3xl font-black text-slate-900">{{ queueStore.metrics?.total ?? 0 }}</p>
                </article>
                <article class="rounded-3xl bg-white p-5 shadow-sm">
                    <div class="mb-3 flex items-center justify-between">
                        <p class="text-sm text-slate-500">في الانتظار</p>
                        <Users class="h-5 w-5 text-amber-500" />
                    </div>
                    <p class="text-3xl font-black text-amber-600">{{ queueStore.metrics?.waiting ?? 0 }}</p>
                </article>
                <article class="rounded-3xl bg-white p-5 shadow-sm">
                    <div class="mb-3 flex items-center justify-between">
                        <p class="text-sm text-slate-500">مكتمل</p>
                        <CheckCircle class="h-5 w-5 text-green-500" />
                    </div>
                    <p class="text-3xl font-black text-green-600">{{ queueStore.metrics?.completed ?? 0 }}</p>
                </article>
                <article class="rounded-3xl bg-white p-5 shadow-sm">
                    <div class="mb-3 flex items-center justify-between">
                        <p class="text-sm text-slate-500">متوسط وقت الخدمة</p>
                        <Timer class="h-5 w-5 text-indigo-500" />
                    </div>
                    <p class="text-3xl font-black text-indigo-600">{{ avgHandlingMinutes }} د</p>
                </article>
            </section>

            <section class="grid gap-6 lg:grid-cols-3">
                <div class="rounded-3xl bg-white p-6 shadow-sm lg:col-span-2">
                    <h2 class="mb-4 flex items-center gap-2 text-lg font-bold text-slate-800">
                        <UserCheck class="h-5 w-5" />
                        أداء الموظفين
                    </h2>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <article
                            v-for="teller in queueStore.tellerPerformance"
                            :key="teller.id"
                            class="rounded-2xl border border-slate-100 bg-slate-50/60 p-4"
                        >
                            <div class="mb-3 flex items-start justify-between gap-3">
                                <div>
                                    <p class="font-semibold text-slate-800">{{ teller.name }}</p>
                                    <p class="mt-0.5 text-sm text-slate-500">{{ teller.counter_name ?? '—' }}</p>
                                </div>
                                <span
                                    class="shrink-0 rounded-full px-2 py-1 text-xs font-semibold"
                                    :class="teller.is_active ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'"
                                >
                                    {{ teller.is_active ? 'نشط' : 'غير نشط' }}
                                </span>
                            </div>
                            <dl class="grid grid-cols-3 gap-2 text-center text-sm">
                                <div class="rounded-xl bg-white px-2 py-2">
                                    <dt class="text-xs text-slate-500">مكتمل</dt>
                                    <dd class="font-bold text-slate-800">{{ teller.completed_today }}</dd>
                                </div>
                                <div class="rounded-xl bg-white px-2 py-2">
                                    <dt class="text-xs text-slate-500">خدمة</dt>
                                    <dd class="font-bold text-slate-800">{{ teller.serving_now }}</dd>
                                </div>
                                <div class="rounded-xl bg-white px-2 py-2">
                                    <dt class="text-xs text-slate-500">متوسط</dt>
                                    <dd class="font-bold text-slate-800">{{ Math.round(teller.avg_handling_seconds / 60) }} د</dd>
                                </div>
                            </dl>
                        </article>
                        <p v-if="!queueStore.tellerPerformance?.length" class="col-span-full py-8 text-center text-sm text-slate-400">
                            لا توجد بيانات
                        </p>
                    </div>
                </div>

                <div class="space-y-4">
                    <div class="rounded-3xl bg-white p-6 shadow-sm">
                        <h3 class="mb-3 flex items-center gap-2 font-bold text-slate-800">
                            <Clock3 class="h-5 w-5 text-blue-500" />
                            متوسط الانتظار
                        </h3>
                        <p class="text-4xl font-black text-blue-600">{{ avgWaitMinutes }} دقيقة</p>
                    </div>

                    <div class="rounded-3xl bg-white p-6 shadow-sm">
                        <h3 class="mb-3 font-bold text-slate-800">شبابيك الموظفين</h3>
                        <ul class="space-y-2">
                            <li
                                v-for="teller in queueStore.tellers"
                                :key="teller.id"
                                class="flex items-center justify-between rounded-xl bg-slate-50 px-3 py-2 text-sm"
                            >
                                <span>{{ teller.name }}</span>
                                <span class="font-semibold text-blue-600">{{ teller.counter_name ?? '—' }}</span>
                            </li>
                        </ul>
                    </div>
                </div>
            </section>

            <AdminUserManagement v-if="authStore.canManageUsers" />
        </main>

        <div
            v-if="showResetConfirm"
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-6"
            @click.self="showResetConfirm = false"
        >
            <div class="w-full max-w-md rounded-3xl bg-white p-6 shadow-2xl">
                <h3 class="text-xl font-bold text-slate-900">تأكيد تصفير اليوم</h3>
                <p class="mt-3 text-sm text-slate-600">
                    سيتم حذف جميع تذاكر اليوم وإعادة العداد من 1. هذا الإجراء لا يمكن التراجع عنه.
                </p>
                <div class="mt-6 flex gap-3">
                    <button
                        class="flex-1 rounded-xl bg-red-600 py-3 font-bold text-white hover:bg-red-700 disabled:opacity-60"
                        :disabled="actionLoading"
                        @click="handleResetDay"
                    >
                        {{ actionLoading ? 'جاري التصفير...' : 'تأكيد التصفير' }}
                    </button>
                    <button
                        class="flex-1 rounded-xl border border-slate-200 py-3 font-semibold text-slate-700 hover:bg-slate-50"
                        @click="showResetConfirm = false"
                    >
                        إلغاء
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
