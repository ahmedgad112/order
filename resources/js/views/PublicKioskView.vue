<script setup>
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { RouterLink } from 'vue-router';
import { Ticket, User, Hash, FileText, Lock, Search, ExternalLink, FilePlus, ClipboardList, GraduationCap, ArrowRight, Files } from 'lucide-vue-next';
import { useQueueStore } from '../stores/queueStore';
import AppNavbar from '../components/AppNavbar.vue';
import IssuedTicketModal from '../components/IssuedTicketModal.vue';

const queueStore = useQueueStore();

const form = ref({
    full_name: '',
    national_id: '',
    request_type: '',
    completion_step: '',
    college: '',
    order_number: '',
});

const showModal = ref(false);
const issuedTicket = ref(null);
const fieldErrors = ref({});
let stopAutoRefresh = null;
let unsubscribeEcho = null;

function restrictDigitInput(event, maxLength) {
    const digits = event.target.value.replace(/\D/g, '').slice(0, maxLength);
    event.target.value = digits;

    return digits;
}

function onNationalIdInput(event) {
    form.value.national_id = restrictDigitInput(event, 14);
}

function onOrderNumberInput(event) {
    form.value.order_number = restrictDigitInput(event, 9);
}

const enabledRequestTypes = computed(() => (
    (queueStore.system.request_types ?? []).filter((type) => type.enabled)
));

const colleges = computed(() => (queueStore.system.colleges ?? []).filter((college) => college.is_active !== false));

const selectedRequestType = computed(() => (
    enabledRequestTypes.value.find((type) => type.value === form.value.request_type) ?? null
));

const canIssueTicket = computed(() => queueStore.isAcceptingTickets && queueStore.isNewStudentOpen);

watch(enabledRequestTypes, (types) => {
    if (form.value.request_type && !types.some((type) => type.value === form.value.request_type)) {
        form.value.request_type = '';
        form.value.completion_step = '';
        form.value.college = '';
        form.value.order_number = '';
    }
});

watch(() => form.value.request_type, () => {
    form.value.completion_step = '';
    form.value.college = '';
});

function emptyForm() {
    return {
        full_name: '',
        national_id: '',
        request_type: '',
        completion_step: '',
        college: '',
        order_number: '',
    };
}

function validateClient() {
    const errors = {};

    if (!form.value.full_name || form.value.full_name.trim().length < 3) {
        errors.full_name = 'الاسم الكامل مطلوب (3 أحرف على الأقل).';
    }

    if (!/^\d{14}$/.test(form.value.national_id)) {
        errors.national_id = 'يجب أن يتكون الرقم القومي من 14 رقمًا.';
    }

    if (!form.value.request_type) {
        errors.request_type = 'يجب اختيار نوع الطلب.';
    } else {
        if (selectedRequestType.value?.requires_completion_service && !form.value.completion_step) {
            errors.completion_step = 'يجب اختيار الخدمة المراد استكمال أوراقها.';
        }

        if (selectedRequestType.value) {
            if (!form.value.college || form.value.college.trim().length < 3) {
                errors.college = selectedRequestType.value.college_mode === 'select'
                    ? 'يجب اختيار الكلية الواردة في بطاقة الترشيح.'
                    : 'يجب كتابة الكلية المراد الالتحاق بها.';
            } else if (
                selectedRequestType.value.college_mode === 'select'
                && !colleges.value.some((college) => college.value === form.value.college)
            ) {
                errors.college = 'يجب اختيار الكلية الواردة في بطاقة الترشيح.';
            }
        }

        if (!/^\d{9}$/.test(form.value.order_number)) {
            errors.order_number = 'يجب أن يتكون رقم الطلب من 9 أرقام.';
        }
    }

    fieldErrors.value = errors;
    return Object.keys(errors).length === 0;
}

async function submitForm() {
    if (!validateClient()) {
        return;
    }

    try {
        const payload = {
            student_kind: 'new_student',
            full_name: form.value.full_name.trim(),
            national_id: form.value.national_id,
            request_type: form.value.request_type,
            college: form.value.college.trim(),
            order_number: form.value.order_number,
        };

        if (selectedRequestType.value?.requires_completion_service) {
            payload.completion_step = form.value.completion_step;
        }

        const ticket = await queueStore.issueTicket(payload);

        issuedTicket.value = ticket;
        showModal.value = true;
        form.value = emptyForm();
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
        <AppNavbar title="نظام إدارة الأدوار" subtitle="إصدار تذكرة جديدة" max-width="5xl">
            <div class="flex items-center gap-3 rounded-2xl bg-blue-600 px-4 py-2 text-white shadow-lg sm:px-5 sm:py-3">
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
                class="mb-6 inline-flex items-center gap-2 text-sm font-semibold text-slate-600 hover:text-blue-700"
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
            <div
                v-else-if="!queueStore.isNewStudentOpen"
                class="mb-6 rounded-3xl border border-amber-200 bg-amber-50 p-6 text-center"
            >
                <Lock class="mx-auto mb-3 h-10 w-10 text-amber-500" />
                <h2 class="text-xl font-bold text-amber-800">تقديم الطلاب الجدد مغلق حالياً</h2>
                <p class="mt-2 text-amber-700">لا يمكن إصدار تذاكر للطلاب الجدد في الوقت الحالي.</p>
            </div>

            <div class="rounded-3xl border border-slate-200 bg-white p-8 shadow-xl" :class="{ 'opacity-60': !canIssueTicket }">
                <h2 class="mb-8 text-center text-3xl font-bold text-slate-800">احصل على تذكرتك</h2>

                <p v-if="fieldErrors.general" class="mb-6 rounded-xl bg-red-50 px-4 py-3 text-center text-red-700">
                    {{ fieldErrors.general }}
                </p>

                <form class="space-y-6" @submit.prevent="submitForm">
                    <fieldset :disabled="!canIssueTicket" class="space-y-6">
                    <div>
                        <label class="mb-2 flex items-center gap-2 text-sm font-semibold text-slate-700">
                            <User class="h-4 w-4" />
                            الاسم الكامل
                        </label>
                        <input
                            v-model="form.full_name"
                            type="text"
                            class="w-full rounded-2xl border border-slate-200 px-5 py-4 text-lg outline-none transition focus:border-blue-500 focus:ring-4 focus:ring-blue-100"
                            placeholder="مثال: أحمد محمود علي"
                        />
                        <p v-if="fieldErrors.full_name" class="mt-2 text-sm text-red-600">{{ fieldErrors.full_name }}</p>
                    </div>

                    <div>
                        <label class="mb-2 flex items-center gap-2 text-sm font-semibold text-slate-700">
                            <Hash class="h-4 w-4" />
                            الرقم القومي (14 رقم)
                        </label>
                        <input
                            :value="form.national_id"
                            inputmode="numeric"
                            type="text"
                            maxlength="14"
                            pattern="[0-9]*"
                            autocomplete="off"
                            class="w-full rounded-2xl border border-slate-200 px-5 py-4 text-lg tracking-widest outline-none transition focus:border-blue-500 focus:ring-4 focus:ring-blue-100"
                            placeholder="2 9 0 0 1 0 1 2 3 4 5 6 7 8"
                            @input="onNationalIdInput"
                        />
                        <p v-if="fieldErrors.national_id" class="mt-2 text-sm text-red-600">{{ fieldErrors.national_id }}</p>
                    </div>

                    <div>
                        <label class="mb-2 flex items-center gap-2 text-sm font-semibold text-slate-700">
                            <ClipboardList class="h-4 w-4" />
                            نوع الطلب
                        </label>
                        <p v-if="!enabledRequestTypes.length" class="rounded-2xl bg-amber-50 px-4 py-3 text-sm text-amber-800">
                            لا توجد أنواع طلبات متاحة حالياً.
                        </p>
                        <div v-else class="grid gap-3">
                            <label
                                v-for="type in enabledRequestTypes"
                                :key="type.value"
                                class="flex cursor-pointer items-center gap-3 rounded-2xl border px-5 py-4 transition"
                                :class="form.request_type === type.value
                                    ? 'border-blue-500 bg-blue-50 ring-4 ring-blue-100'
                                    : 'border-slate-200 hover:border-blue-300'"
                            >
                                <input
                                    v-model="form.request_type"
                                    type="radio"
                                    :value="type.value"
                                    class="h-4 w-4 accent-blue-600"
                                />
                                <span class="text-base font-semibold text-slate-800">{{ type.label }}</span>
                            </label>
                        </div>
                        <p v-if="fieldErrors.request_type" class="mt-2 text-sm text-red-600">{{ fieldErrors.request_type }}</p>
                    </div>

                    <div v-if="selectedRequestType?.requires_completion_service">
                        <label class="mb-2 flex items-center gap-2 text-sm font-semibold text-slate-700">
                            <Files class="h-4 w-4" />
                            الخدمة المراد استكمال أوراقها
                        </label>
                        <div class="grid gap-3 sm:grid-cols-2">
                            <label
                                v-for="service in selectedRequestType.completion_services"
                                :key="service.value"
                                class="flex cursor-pointer items-center gap-3 rounded-2xl border px-5 py-4 transition"
                                :class="form.completion_step === service.value
                                    ? 'border-indigo-500 bg-indigo-50 ring-4 ring-indigo-100'
                                    : 'border-slate-200 hover:border-indigo-300'"
                            >
                                <input
                                    v-model="form.completion_step"
                                    type="radio"
                                    :value="service.value"
                                    class="h-4 w-4 accent-indigo-600"
                                />
                                <span class="text-base font-semibold text-slate-800">{{ service.label }}</span>
                            </label>
                        </div>
                        <p v-if="fieldErrors.completion_step" class="mt-2 text-sm text-red-600">{{ fieldErrors.completion_step }}</p>
                    </div>

                    <div v-if="selectedRequestType">
                        <label class="mb-2 flex items-center gap-2 text-sm font-semibold text-slate-700">
                            <GraduationCap class="h-4 w-4" />
                            {{ selectedRequestType.college_label }}
                        </label>
                        <select
                            v-if="selectedRequestType.college_mode === 'select'"
                            v-model="form.college"
                            class="w-full rounded-2xl border border-slate-200 px-5 py-4 text-lg outline-none transition focus:border-blue-500 focus:ring-4 focus:ring-blue-100"
                        >
                            <option value="" disabled>اختر الكلية</option>
                            <option v-for="college in colleges" :key="college.value" :value="college.value">
                                {{ college.label }}
                            </option>
                        </select>
                        <input
                            v-else
                            v-model="form.college"
                            type="text"
                            class="w-full rounded-2xl border border-slate-200 px-5 py-4 text-lg outline-none transition focus:border-blue-500 focus:ring-4 focus:ring-blue-100"
                            placeholder="اكتب اسم الكلية المراد الالتحاق بها"
                        />
                        <p v-if="fieldErrors.college" class="mt-2 text-sm text-red-600">{{ fieldErrors.college }}</p>
                    </div>

                    <div v-if="selectedRequestType">
                        <label class="mb-2 flex items-center gap-2 text-sm font-semibold text-slate-700">
                            <FileText class="h-4 w-4" />
                            رقم الطلب (9 أرقام)
                        </label>
                        <input
                            :value="form.order_number"
                            inputmode="numeric"
                            type="text"
                            maxlength="9"
                            pattern="[0-9]*"
                            autocomplete="off"
                            class="w-full rounded-2xl border border-slate-200 px-5 py-4 text-lg tracking-widest outline-none transition focus:border-blue-500 focus:ring-4 focus:ring-blue-100"
                            placeholder="أدخل رقم الطلب"
                            @input="onOrderNumberInput"
                        />
                        <p v-if="fieldErrors.order_number" class="mt-2 text-sm text-red-600">{{ fieldErrors.order_number }}</p>
                    </div>

                    <button
                        type="submit"
                        :disabled="queueStore.loading || !canIssueTicket"
                        class="flex w-full items-center justify-center gap-3 rounded-2xl bg-blue-600 px-6 py-5 text-xl font-bold text-white transition hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-60"
                    >
                        <Ticket class="h-6 w-6" />
                        {{ !canIssueTicket
                            ? (!queueStore.isSystemOpen
                                ? 'النظام مغلق'
                                : (!queueStore.isAcceptingTickets ? 'انتهى استقبال الطلبات' : 'التقديم مغلق حالياً'))
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

            <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-xl">
                    <p class="mb-4 text-center text-sm text-slate-500">قدّم طلب الالتحاق من هنا</p>
                    <a
                        href="https://batechu.com/admission"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="flex w-full items-center justify-center gap-2 rounded-2xl bg-emerald-600 px-5 py-3 text-base font-bold text-white transition hover:bg-emerald-700"
                    >
                        <FilePlus class="h-5 w-5" />
                        تقدم الطلب
                    </a>
                </div>
                <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-xl">
                    <p class="mb-4 text-center text-sm text-slate-500">اعرف حالة طلبك في أي وقت</p>
                    <a
                        href="https://batechu.com/admission/track"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="flex w-full items-center justify-center gap-2 rounded-2xl bg-indigo-600 px-5 py-3 text-base font-bold text-white transition hover:bg-indigo-700"
                    >
                        <ExternalLink class="h-5 w-5" />
                        تتبع طلبك
                    </a>
                </div>
            </div>
        </main>

        <IssuedTicketModal
            v-if="showModal && issuedTicket"
            :ticket="issuedTicket"
            @close="closeModal"
        />
    </div>
</template>
