<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue';
import {
    Archive,
    BarChart3,
    CheckCircle,
    ClipboardList,
    Clock3,
    GraduationCap,
    Lock,
    Power,
    Timer,
    Unlock,
    Users,
    UserCheck,
} from 'lucide-vue-next';
import { useAuthStore } from '../stores/authStore';
import { useQueueStore } from '../stores/queueStore';
import AdminCatalogManagement from '../components/AdminCatalogManagement.vue';
import AdminUserManagement from '../components/AdminUserManagement.vue';
import AppNavbar from '../components/AppNavbar.vue';

const authStore = useAuthStore();
const queueStore = useQueueStore();
const navbarSubtitle = computed(() => {
    const role = authStore.user?.role_label ?? 'الإدارة';
    const hint = authStore.canControlSystem
        ? 'إدارة النظام والحسابات والتقارير اليومية'
        : 'متابعة التقارير والتشغيل اليومي';

    return `${role} — ${hint}`;
});

const closeMessage = ref('');
const actionLoading = ref(false);
const actionFeedback = ref('');
const actionError = ref('');
const showEndDayConfirm = ref(false);
const showOpenDayConfirm = ref(false);
let stopAutoRefresh = null;
let unsubscribeEcho = null;

const avgWaitMinutes = computed(() => {
    const seconds = queueStore.metrics?.avg_wait_seconds ?? 0;
    return Math.round(seconds / 60);
});

const avgHandlingMinutes = computed(() => {
    const seconds = queueStore.metrics?.avg_handling_seconds ?? 0;
    return Math.round(seconds / 60);
});

const requestTypes = computed(() => queueStore.system.request_types ?? []);
const enabledRequestTypeCount = computed(() => requestTypes.value.filter((type) => type.enabled).length);
const studentKinds = computed(() => {
    const kinds = queueStore.system.student_kinds ?? [];

    if (kinds.length) {
        return kinds;
    }

    return [
        { value: 'new_student', label: 'طالب جديد', enabled: true },
        { value: 'current_student', label: 'طالب حالي (فرقة ثانية)', enabled: true },
    ];
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

async function handleEndDay() {
    actionLoading.value = true;
    actionError.value = '';
    actionFeedback.value = '';
    try {
        const result = await queueStore.endDay();
        actionFeedback.value = `${result.message} (تم أرشفة ${result.archived_tickets} طلب)`;
        showEndDayConfirm.value = false;
        await refresh();
    } catch (err) {
        actionError.value = err.response?.data?.message
            ?? err.response?.data?.errors?.system?.[0]
            ?? 'تعذر إنهاء اليوم.';
    } finally {
        actionLoading.value = false;
    }
}

async function handleOpenDay() {
    actionLoading.value = true;
    actionError.value = '';
    actionFeedback.value = '';
    try {
        const result = await queueStore.openDay();
        actionFeedback.value = result.message;
        showOpenDayConfirm.value = false;
        await refresh();
    } catch (err) {
        actionError.value = err.response?.data?.message
            ?? err.response?.data?.errors?.system?.[0]
            ?? 'تعذر فتح اليوم.';
    } finally {
        actionLoading.value = false;
    }
}

async function toggleRequestType(typeValue, enabled) {
    if (!enabled && enabledRequestTypeCount.value <= 1) {
        actionError.value = 'يجب إبقاء نوع طلب واحد على الأقل ظاهراً.';
        return;
    }

    actionLoading.value = true;
    actionError.value = '';
    actionFeedback.value = '';
    try {
        const next = requestTypes.value
            .filter((type) => (type.value === typeValue ? enabled : type.enabled))
            .map((type) => type.value);
        const result = await queueStore.updateRequestTypes(next);
        actionFeedback.value = result.message;
    } catch (err) {
        actionError.value = err.response?.data?.message
            ?? err.response?.data?.errors?.enabled_request_types?.[0]
            ?? 'تعذر تحديث أنواع الطلبات.';
    } finally {
        actionLoading.value = false;
    }
}

async function toggleStudentKind(kindValue, enabled) {
    actionLoading.value = true;
    actionError.value = '';
    actionFeedback.value = '';
    try {
        const next = studentKinds.value
            .filter((kind) => (kind.value === kindValue ? enabled : kind.enabled))
            .map((kind) => kind.value);
        const result = await queueStore.updateStudentKinds(next);
        actionFeedback.value = result.message;
    } catch (err) {
        actionError.value = err.response?.data?.message
            ?? err.response?.data?.errors?.enabled_student_kinds?.[0]
            ?? 'تعذر تحديث أنواع الطلاب في شاشة الاختيار.';
    } finally {
        actionLoading.value = false;
    }
}

onMounted(() => {
    refresh();
    unsubscribeEcho = queueStore.subscribeEcho();
    stopAutoRefresh = queueStore.startAutoRefresh(() => queueStore.fetchAdminDashboard(), 8000);
});

onUnmounted(() => {
    unsubscribeEcho?.();
    stopAutoRefresh?.();
});
</script>

<template>
    <div class="min-h-screen bg-slate-100">
        <AppNavbar title="لوحة الإدارة" :subtitle="navbarSubtitle" />

        <main class="mx-auto max-w-7xl space-y-6 px-4 py-6 sm:px-6">
            <section
                class="rounded-3xl border p-6 shadow-sm"
                :class="queueStore.isSystemOpen
                    ? (queueStore.isDayOpen ? 'border-green-200 bg-green-50' : 'border-amber-200 bg-amber-50')
                    : 'border-red-200 bg-red-50'"
            >
                <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <div class="mb-2 flex flex-wrap items-center gap-2">
                            <component :is="queueStore.isSystemOpen ? Unlock : Lock" class="h-5 w-5" />
                            <h2 class="text-lg font-bold text-slate-900">حالة النظام</h2>
                            <span
                                class="rounded-full px-3 py-1 text-xs font-bold"
                                :class="queueStore.isSystemOpen ? 'bg-green-200 text-green-800' : 'bg-red-200 text-red-800'"
                            >
                                {{ queueStore.isSystemOpen ? 'النظام شغال' : 'النظام مغلق' }}
                            </span>
                            <span
                                class="rounded-full px-3 py-1 text-xs font-bold"
                                :class="queueStore.isDayOpen ? 'bg-blue-100 text-blue-800' : 'bg-amber-200 text-amber-900'"
                            >
                                {{ queueStore.isDayOpen ? 'اليوم مفتوح' : 'اليوم مؤرشف' }}
                            </span>
                        </div>
                        <p class="text-sm text-slate-600">
                            {{ !queueStore.isSystemOpen
                                ? (queueStore.system.closed_message || 'النظام مغلق ولا يمكن إصدار تذاكر جديدة.')
                                : (queueStore.isDayOpen
                                    ? 'النظام يستقبل طلبات جديدة والموظفين يقدروا يكملوا الشغل.'
                                    : (queueStore.system.day_ended_message || 'انتهى استقبال الطلبات اليوم. النظام شغال لمتابعة الطلبات الحالية.')) }}
                        </p>
                        <p v-if="queueStore.system.day_ended_at" class="mt-1 text-xs text-slate-500">
                            آخر إنهاء لليوم: {{ new Date(queueStore.system.day_ended_at).toLocaleString('ar-EG') }}
                        </p>
                        <p v-else-if="queueStore.system.last_reset_at" class="mt-1 text-xs text-slate-500">
                            آخر فتح لليوم: {{ new Date(queueStore.system.last_reset_at).toLocaleString('ar-EG') }}
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
                            v-if="queueStore.isDayOpen"
                            class="flex items-center gap-2 rounded-xl border border-amber-300 bg-amber-50 px-4 py-2 font-semibold text-amber-800 hover:bg-amber-100 disabled:opacity-60"
                            :disabled="actionLoading"
                            @click="showEndDayConfirm = true"
                        >
                            <Archive class="h-4 w-4" />
                            انتهاء اليوم
                        </button>
                        <button
                            v-else
                            class="flex items-center gap-2 rounded-xl border border-blue-300 bg-blue-50 px-4 py-2 font-semibold text-blue-800 hover:bg-blue-100 disabled:opacity-60"
                            :disabled="actionLoading"
                            @click="showOpenDayConfirm = true"
                        >
                            <Power class="h-4 w-4" />
                            فتح اليوم
                        </button>
                    </div>
                    <p v-else class="text-sm font-semibold text-slate-500">
                        فتح النظام وإغلاقه وإنهاء اليوم متاح للسوبر أدمن فقط.
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

            <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="mb-4 flex items-center gap-2">
                    <GraduationCap class="h-5 w-5 text-indigo-600" />
                    <h2 class="text-lg font-bold text-slate-900">اختر نوع الطالب للمتابعة</h2>
                </div>
                <p class="mb-4 text-sm text-slate-600">
                    فعّل أو ألغِ الخيارات اللي تظهر للطالب في شاشة البداية. الخيار الملغي مش هيظهر خالص.
                </p>
                <div class="grid gap-3 sm:grid-cols-2">
                    <label
                        v-for="kind in studentKinds"
                        :key="kind.value"
                        class="flex items-center justify-between gap-3 rounded-2xl border px-4 py-3"
                        :class="kind.enabled ? 'border-indigo-200 bg-indigo-50' : 'border-slate-200 bg-slate-50'"
                    >
                        <span>
                            <span class="block text-sm font-semibold text-slate-800">{{ kind.label }}</span>
                            <span
                                class="mt-1 inline-flex rounded-full px-2 py-0.5 text-[11px] font-bold"
                                :class="kind.enabled ? 'bg-green-100 text-green-700' : 'bg-slate-200 text-slate-600'"
                            >
                                {{ kind.enabled ? 'مفعّل' : 'ملغي' }}
                            </span>
                        </span>
                        <input
                            type="checkbox"
                            class="h-4 w-4 accent-indigo-600"
                            :checked="kind.enabled"
                            :disabled="!authStore.canControlSystem || actionLoading"
                            @change="toggleStudentKind(kind.value, $event.target.checked)"
                        />
                    </label>
                </div>
                <p v-if="!authStore.canControlSystem" class="mt-3 text-sm font-semibold text-slate-500">
                    تفعيل أنواع الطلاب وإلغاؤها متاح للسوبر أدمن فقط.
                </p>
            </section>

            <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="mb-4 flex items-center gap-2">
                    <ClipboardList class="h-5 w-5 text-indigo-600" />
                    <h2 class="text-lg font-bold text-slate-900">أنواع الطلب الظاهرة للطالب</h2>
                </div>
                <p class="mb-4 text-sm text-slate-600">
                    تحكم في الأنواع التي تظهر في شاشة إصدار التذكرة. رقم الطلب يظهر للطالب بعد اختيار النوع.
                </p>
                <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    <label
                        v-for="type in requestTypes"
                        :key="type.value"
                        class="flex items-center justify-between gap-3 rounded-2xl border px-4 py-3"
                        :class="type.enabled ? 'border-indigo-200 bg-indigo-50' : 'border-slate-200 bg-slate-50'"
                    >
                        <span class="text-sm font-semibold text-slate-800">{{ type.label }}</span>
                        <input
                            type="checkbox"
                            class="h-4 w-4 accent-indigo-600"
                            :checked="type.enabled"
                            :disabled="!authStore.canControlSystem || actionLoading || (type.enabled && enabledRequestTypeCount <= 1)"
                            @change="toggleRequestType(type.value, $event.target.checked)"
                        />
                    </label>
                </div>
                <p v-if="!authStore.canControlSystem" class="mt-3 text-sm font-semibold text-slate-500">
                    إظهار أنواع الطلب وإخفاؤها متاح للسوبر أدمن فقط.
                </p>
            </section>

            <AdminCatalogManagement />

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
            v-if="showEndDayConfirm"
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-6"
            @click.self="showEndDayConfirm = false"
        >
            <div class="w-full max-w-md rounded-3xl bg-white p-6 shadow-2xl">
                <h3 class="text-xl font-bold text-slate-900">تأكيد انتهاء اليوم</h3>
                <p class="mt-3 text-sm text-slate-600">
                    هيتوقف استقبال الناس الجديدة ويتأرشف اليوم. السيستم هيفضل شغال عشان تكملوا الطلبات الحالية، ومن غير ما يتقفل.
                </p>
                <div class="mt-6 flex gap-3">
                    <button
                        class="flex-1 rounded-xl bg-amber-600 py-3 font-bold text-white hover:bg-amber-700 disabled:opacity-60"
                        :disabled="actionLoading"
                        @click="handleEndDay"
                    >
                        {{ actionLoading ? 'جاري إنهاء اليوم...' : 'تأكيد انتهاء اليوم' }}
                    </button>
                    <button
                        class="flex-1 rounded-xl border border-slate-200 py-3 font-semibold text-slate-700 hover:bg-slate-50"
                        @click="showEndDayConfirm = false"
                    >
                        إلغاء
                    </button>
                </div>
            </div>
        </div>

        <div
            v-if="showOpenDayConfirm"
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-6"
            @click.self="showOpenDayConfirm = false"
        >
            <div class="w-full max-w-md rounded-3xl bg-white p-6 shadow-2xl">
                <h3 class="text-xl font-bold text-slate-900">تأكيد فتح اليوم</h3>
                <p class="mt-3 text-sm text-slate-600">
                    هيبدأ يوم جديد والعداد من الصفر. طلبات اليوم السابق هتفضل موجودة في الأرشيف.
                </p>
                <div class="mt-6 flex gap-3">
                    <button
                        class="flex-1 rounded-xl bg-blue-600 py-3 font-bold text-white hover:bg-blue-700 disabled:opacity-60"
                        :disabled="actionLoading"
                        @click="handleOpenDay"
                    >
                        {{ actionLoading ? 'جاري فتح اليوم...' : 'فتح اليوم' }}
                    </button>
                    <button
                        class="flex-1 rounded-xl border border-slate-200 py-3 font-semibold text-slate-700 hover:bg-slate-50"
                        @click="showOpenDayConfirm = false"
                    >
                        إلغاء
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
