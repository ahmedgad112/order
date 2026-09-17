<script setup>
import { computed, onMounted, ref } from 'vue';
import { ClipboardList, Pencil, Plus, Trash2 } from 'lucide-vue-next';
import { useAuthStore } from '../stores/authStore';
import { useQueueStore } from '../stores/queueStore';

const authStore = useAuthStore();
const queueStore = useQueueStore();

const showForm = ref(false);
const editingItem = ref(null);
const formError = ref('');
const actionError = ref('');
const saving = ref(false);
const feedback = ref('');

const emptyForm = () => ({
    label: '',
    college_mode: 'text',
    college_label: '',
    counter_name: '',
    requires_completion_service: false,
    completion_services: [],
    enabled: true,
});

const form = ref(emptyForm());

const isEditing = computed(() => Boolean(editingItem.value));
const canManage = computed(() => authStore.canControlSystem);
const requestTypes = computed(() => {
    const admin = queueStore.adminRequestTypes ?? [];

    return admin.length ? admin : (queueStore.system.request_types ?? []);
});
const enabledCount = computed(() => requestTypes.value.filter((type) => type.enabled).length);
const completionServiceOptions = computed(() => queueStore.system.completion_service_options ?? []);
const formTitle = computed(() => (isEditing.value ? 'تعديل نوع الطلب' : 'إضافة نوع طلب'));

onMounted(() => {
    if (canManage.value) {
        queueStore.fetchRequestTypes().catch(() => {});
    }
});

function openCreate() {
    editingItem.value = null;
    form.value = emptyForm();
    formError.value = '';
    showForm.value = true;
}

function openEdit(item) {
    editingItem.value = item;
    form.value = {
        label: item.label,
        college_mode: item.college_mode ?? 'text',
        college_label: item.college_label ?? '',
        counter_name: item.counter_name ?? '',
        requires_completion_service: Boolean(item.requires_completion_service),
        completion_services: (item.completion_services ?? []).map((service) => service.value),
        enabled: item.enabled !== false,
    };
    formError.value = '';
    showForm.value = true;
}

function closeForm() {
    showForm.value = false;
    editingItem.value = null;
    formError.value = '';
}

function firstError(err, fallback) {
    const errors = err.response?.data?.errors ?? {};
    const firstKey = Object.keys(errors)[0];

    return err.response?.data?.message
        ?? errors[firstKey]?.[0]
        ?? fallback;
}

async function submitForm() {
    saving.value = true;
    formError.value = '';
    feedback.value = '';

    const payload = {
        label: form.value.label.trim(),
        college_mode: form.value.college_mode,
        college_label: form.value.college_label?.trim() || null,
        counter_name: form.value.counter_name?.trim() || null,
        requires_completion_service: form.value.requires_completion_service,
        completion_services: form.value.requires_completion_service
            ? form.value.completion_services
            : [],
        enabled: form.value.enabled,
    };

    try {
        const result = isEditing.value
            ? await queueStore.updateRequestType(editingItem.value.id, payload)
            : await queueStore.createRequestType(payload);

        feedback.value = result.message;
        closeForm();
    } catch (err) {
        formError.value = firstError(err, 'تعذر حفظ نوع الطلب.');
    } finally {
        saving.value = false;
    }
}

async function toggleType(item, enabled) {
    if (!enabled && item.enabled && enabledCount.value <= 1) {
        actionError.value = 'يجب إبقاء نوع طلب واحد على الأقل ظاهراً.';
        return;
    }

    actionError.value = '';
    feedback.value = '';

    try {
        const result = await queueStore.updateRequestType(item.id, { enabled });
        feedback.value = result.message;
    } catch (err) {
        actionError.value = firstError(err, 'تعذر تحديث نوع الطلب.');
        item.enabled = !enabled;
    }
}

async function handleDelete(item) {
    if (!confirm(`هل تريد حذف نوع الطلب "${item.label}"؟`)) {
        return;
    }

    actionError.value = '';
    feedback.value = '';

    try {
        const result = await queueStore.deleteRequestType(item.id);
        feedback.value = result.message;
    } catch (err) {
        actionError.value = firstError(err, 'تعذر حذف نوع الطلب.');
    }
}
</script>

<template>
    <section class="rounded-3xl bg-white p-6 shadow-sm">
        <div class="mb-4 flex items-center justify-between gap-3">
            <div>
                <h2 class="flex items-center gap-2 text-lg font-bold text-slate-800">
                    <ClipboardList class="h-5 w-5 text-indigo-600" />
                    أنواع الطلب
                </h2>
                <p class="mt-1 text-sm text-slate-500">
                    أضف وعدّل أنواع الطلب التي تظهر للطالب في شاشة إصدار التذكرة. النوع الملغي لا يظهر للطالب.
                </p>
            </div>
            <button
                v-if="canManage"
                class="flex items-center gap-1 rounded-xl bg-indigo-600 px-3 py-2 text-xs font-semibold text-white hover:bg-indigo-700"
                @click="openCreate"
            >
                <Plus class="h-3.5 w-3.5" />
                إضافة نوع
            </button>
        </div>

        <p v-if="feedback" class="mb-4 text-sm font-semibold text-green-700">{{ feedback }}</p>
        <p v-if="actionError" class="mb-4 rounded-xl bg-red-50 px-4 py-3 text-sm text-red-700">
            {{ actionError }}
        </p>

        <div class="space-y-2">
            <article
                v-for="type in requestTypes"
                :key="type.id ?? type.value"
                class="flex items-start justify-between gap-3 rounded-2xl border px-4 py-3"
                :class="type.enabled ? 'border-indigo-100 bg-indigo-50/60' : 'border-slate-200 bg-slate-50'"
            >
                <div>
                    <div class="flex flex-wrap items-center gap-2">
                        <p class="font-semibold text-slate-800">{{ type.label }}</p>
                        <span
                            v-if="type.code_prefix"
                            class="rounded-full bg-slate-800 px-2 py-0.5 font-mono text-[11px] font-bold text-white"
                            dir="ltr"
                        >
                            {{ type.code_prefix }}
                        </span>
                    </div>
                    <div class="mt-2 flex flex-wrap items-center gap-2">
                        <span
                            class="inline-flex rounded-full px-2 py-0.5 text-[11px] font-bold"
                            :class="type.enabled ? 'bg-green-100 text-green-700' : 'bg-slate-200 text-slate-600'"
                        >
                            {{ type.enabled ? 'مفعّل' : 'ملغي' }}
                        </span>
                        <span
                            v-if="type.counter_name"
                            class="inline-flex rounded-full bg-blue-100 px-2 py-0.5 text-[11px] font-bold text-blue-700"
                        >
                            {{ type.counter_name }}
                        </span>
                        <span
                            v-if="type.requires_completion_service"
                            class="inline-flex rounded-full bg-amber-100 px-2 py-0.5 text-[11px] font-bold text-amber-700"
                        >
                            استكمال أوراق
                        </span>
                        <span
                            v-if="(type.tickets_count ?? 0) > 0"
                            class="inline-flex rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-bold text-slate-600"
                        >
                            {{ type.tickets_count }} تذكرة
                        </span>
                    </div>
                </div>
                <div v-if="canManage" class="flex shrink-0 items-center gap-2">
                    <input
                        type="checkbox"
                        class="h-4 w-4 accent-indigo-600"
                        :checked="type.enabled"
                        :disabled="saving || (type.enabled && enabledCount <= 1)"
                        title="تفعيل / إلغاء"
                        @change="toggleType(type, $event.target.checked)"
                    />
                    <button
                        class="rounded-xl border border-slate-200 bg-white p-2 text-slate-600 hover:bg-slate-50"
                        title="تعديل"
                        @click="openEdit(type)"
                    >
                        <Pencil class="h-4 w-4" />
                    </button>
                    <button
                        class="rounded-xl border border-red-200 bg-white p-2 text-red-600 hover:bg-red-50"
                        :class="(type.tickets_count ?? 0) > 0 ? 'opacity-50' : ''"
                        :title="(type.tickets_count ?? 0) > 0 ? 'لا يمكن حذف نوع له تذاكر — عطّله بدلاً من ذلك' : 'حذف'"
                        @click="handleDelete(type)"
                    >
                        <Trash2 class="h-4 w-4" />
                    </button>
                </div>
            </article>
            <p v-if="!requestTypes.length" class="py-6 text-center text-sm text-slate-400">لا توجد أنواع طلب</p>
        </div>

        <p v-if="!canManage" class="mt-4 text-sm font-semibold text-slate-500">
            إضافة أنواع الطلب وتعديلها متاح للسوبر أدمن فقط.
        </p>

        <div
            v-if="showForm"
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4"
            @click.self="closeForm"
        >
            <div class="w-full max-w-lg rounded-3xl bg-white p-6 shadow-2xl">
                <h3 class="text-xl font-bold text-slate-900">{{ formTitle }}</h3>

                <form class="mt-6 space-y-4" @submit.prevent="submitForm">
                    <div>
                        <label class="mb-1 block text-sm font-semibold text-slate-700">اسم نوع الطلب</label>
                        <input
                            v-model="form.label"
                            type="text"
                            required
                            minlength="2"
                            class="w-full rounded-xl border border-slate-200 px-4 py-2.5 outline-none focus:border-indigo-500 focus:ring-4 focus:ring-indigo-100"
                            placeholder="مثال: منحة تفوق"
                        />
                    </div>

                    <div class="grid gap-3 sm:grid-cols-2">
                        <div>
                            <label class="mb-1 block text-sm font-semibold text-slate-700">إدخال الكلية</label>
                            <select
                                v-model="form.college_mode"
                                class="w-full rounded-xl border border-slate-200 px-4 py-2.5 outline-none focus:border-indigo-500 focus:ring-4 focus:ring-indigo-100"
                            >
                                <option value="text">نص حر</option>
                                <option value="select">اختيار من قائمة بطاقة الترشيح</option>
                            </select>
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-semibold text-slate-700">عنوان حقل الكلية</label>
                            <input
                                v-model="form.college_label"
                                type="text"
                                class="w-full rounded-xl border border-slate-200 px-4 py-2.5 outline-none focus:border-indigo-500 focus:ring-4 focus:ring-indigo-100"
                                placeholder="مثال: الكلية المراد الالتحاق بها"
                            />
                        </div>
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-semibold text-slate-700">الشباك المعلن في النداء (اختياري)</label>
                        <input
                            v-model="form.counter_name"
                            type="text"
                            maxlength="100"
                            class="w-full rounded-xl border border-slate-200 px-4 py-2.5 outline-none focus:border-indigo-500 focus:ring-4 focus:ring-indigo-100"
                            placeholder="مثال: شباك الدفع"
                        />
                        <p class="mt-1 text-xs text-slate-500">
                            يُنطق في النداء ويظهر على الشاشة بدلاً من شباك الموظف. اتركه فارغاً لاستخدام شباك الموظف.
                        </p>
                    </div>

                    <label class="flex items-center gap-3 rounded-xl bg-slate-50 px-4 py-3">
                        <input
                            v-model="form.requires_completion_service"
                            type="checkbox"
                            class="h-4 w-4 rounded accent-indigo-600"
                        />
                        <span class="text-sm font-semibold text-slate-700">يتطلب اختيار خدمة استكمال</span>
                    </label>

                    <div v-if="form.requires_completion_service" class="rounded-xl border border-slate-200 p-3">
                        <p class="mb-2 text-xs font-semibold text-slate-500">
                            خدمات الاستكمال المتاحة (اتركها فارغة لإتاحة الكل)
                        </p>
                        <div class="grid gap-2 sm:grid-cols-2">
                            <label
                                v-for="service in completionServiceOptions"
                                :key="service.value"
                                class="flex items-center gap-2 text-sm text-slate-700"
                            >
                                <input
                                    v-model="form.completion_services"
                                    type="checkbox"
                                    :value="service.value"
                                    class="h-4 w-4 rounded accent-indigo-600"
                                />
                                {{ service.label }}
                            </label>
                        </div>
                    </div>

                    <label class="flex items-center gap-3 rounded-xl bg-slate-50 px-4 py-3">
                        <input v-model="form.enabled" type="checkbox" class="h-4 w-4 rounded accent-indigo-600" />
                        <span class="text-sm font-semibold text-slate-700">ظاهر للطالب</span>
                    </label>

                    <p v-if="formError" class="rounded-xl bg-red-50 px-4 py-3 text-sm text-red-700">
                        {{ formError }}
                    </p>

                    <div class="flex gap-3 pt-2">
                        <button
                            type="submit"
                            :disabled="saving"
                            class="flex-1 rounded-xl bg-indigo-600 py-3 font-bold text-white hover:bg-indigo-700 disabled:opacity-60"
                        >
                            {{ saving ? 'جاري الحفظ...' : (isEditing ? 'حفظ التعديلات' : 'إضافة النوع') }}
                        </button>
                        <button
                            type="button"
                            class="flex-1 rounded-xl border border-slate-200 py-3 font-semibold text-slate-700 hover:bg-slate-50"
                            @click="closeForm"
                        >
                            إلغاء
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </section>
</template>
