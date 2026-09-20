<script setup>
import { computed, onMounted, ref } from 'vue';
import { Layers, Pencil, Plus, Trash2 } from 'lucide-vue-next';
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
    flow: 'both',
    is_enabled: true,
    sort_order: '',
});

const form = ref(emptyForm());

const canManage = computed(() => authStore.canControlSystem);
const services = computed(() => queueStore.processServices ?? []);
const isEditing = computed(() => Boolean(editingItem.value));
const formTitle = computed(() => (isEditing.value ? 'تعديل خدمة' : 'إضافة خدمة'));

onMounted(() => {
    if (canManage.value) {
        queueStore.fetchProcessServices().catch(() => {});
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
        flow: item.flow ?? 'both',
        is_enabled: item.is_enabled !== false,
        sort_order: item.sort_order ?? '',
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
        flow: form.value.flow,
        is_enabled: form.value.is_enabled,
    };

    if (form.value.sort_order !== '' && form.value.sort_order !== null) {
        payload.sort_order = Number(form.value.sort_order);
    }

    try {
        const result = isEditing.value
            ? await queueStore.updateProcessService(editingItem.value.id, payload)
            : await queueStore.createProcessService(payload);

        feedback.value = result.message;
        closeForm();
    } catch (err) {
        formError.value = firstError(err, 'تعذر حفظ الخدمة.');
    } finally {
        saving.value = false;
    }
}

async function toggleEnabled(item) {
    if (!canManage.value) {
        return;
    }

    actionError.value = '';
    feedback.value = '';

    try {
        const result = await queueStore.updateProcessService(item.id, {
            is_enabled: !item.is_enabled,
        });
        feedback.value = result.message;
    } catch (err) {
        actionError.value = firstError(err, 'تعذر تحديث حالة الخدمة.');
    }
}

async function removeService(item) {
    if (!canManage.value) {
        return;
    }

    if (!confirm(`هل تريد حذف الخدمة "${item.label}"؟`)) {
        return;
    }

    actionError.value = '';
    feedback.value = '';

    try {
        const result = await queueStore.deleteProcessService(item.id);
        feedback.value = result.message;
    } catch (err) {
        actionError.value = firstError(err, 'تعذر حذف الخدمة.');
    }
}
</script>

<template>
    <section class="space-y-4 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-6">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="min-w-0 space-y-1">
                <h2 class="flex items-center gap-2 text-base font-bold text-slate-900 sm:text-lg">
                    <Layers class="h-5 w-5 text-indigo-600" />
                    خدمات المعالجة
                </h2>
                <p class="text-sm text-slate-500">
                    أضف أو عدّل أو احذف أي خدمة، بما فيها الخدمات القديمة. غيّر الترتيب والنطاق والحالة بحرية.
                </p>
            </div>
            <button
                v-if="canManage"
                type="button"
                class="inline-flex items-center gap-2 rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700"
                @click="openCreate"
            >
                <Plus class="h-4 w-4" />
                إضافة خدمة
            </button>
        </div>

        <p
            v-if="feedback"
            class="rounded-xl bg-emerald-50 px-3 py-2 text-sm font-medium text-emerald-700"
        >
            {{ feedback }}
        </p>
        <p
            v-if="actionError"
            class="rounded-xl bg-rose-50 px-3 py-2 text-sm font-medium text-rose-700"
        >
            {{ actionError }}
        </p>

        <div class="overflow-x-auto">
            <table class="min-w-full border-collapse text-sm">
                <thead>
                    <tr class="border-b border-slate-200 text-start">
                        <th class="px-3 py-3 font-bold text-slate-700">الترتيب</th>
                        <th class="px-3 py-3 font-bold text-slate-700">الاسم</th>
                        <th class="px-3 py-3 font-bold text-slate-700">النطاق</th>
                        <th class="px-3 py-3 font-bold text-slate-700">النوع</th>
                        <th class="px-3 py-3 font-bold text-slate-700">الحالة</th>
                        <th class="px-3 py-3 font-bold text-slate-700">إجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="item in services"
                        :key="item.id"
                        class="border-b border-slate-100"
                    >
                        <td class="px-3 py-3 text-slate-600">{{ item.sort_order }}</td>
                        <td class="px-3 py-3 font-semibold text-slate-900">{{ item.label }}</td>
                        <td class="px-3 py-3 text-slate-600">{{ item.flow_label }}</td>
                        <td class="px-3 py-3">
                            <span
                                class="rounded-full px-2 py-1 text-xs font-semibold"
                                :class="item.is_system ? 'bg-slate-100 text-slate-700' : 'bg-indigo-50 text-indigo-700'"
                            >
                                {{ item.is_system ? 'أساسية' : 'مخصصة' }}
                            </span>
                        </td>
                        <td class="px-3 py-3">
                            <button
                                type="button"
                                class="rounded-full px-2.5 py-1 text-xs font-semibold"
                                :class="item.is_enabled ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700'"
                                :disabled="!canManage"
                                @click="toggleEnabled(item)"
                            >
                                {{ item.is_enabled ? 'مفعّلة' : 'موقوفة' }}
                            </button>
                        </td>
                        <td class="px-3 py-3">
                            <div class="flex flex-wrap gap-2">
                                <button
                                    v-if="canManage"
                                    type="button"
                                    class="inline-flex items-center gap-1 rounded-lg border border-slate-200 px-2.5 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                                    @click="openEdit(item)"
                                >
                                    <Pencil class="h-3.5 w-3.5" />
                                    تعديل
                                </button>
                                <button
                                    v-if="canManage"
                                    type="button"
                                    class="inline-flex items-center gap-1 rounded-lg border border-rose-200 px-2.5 py-1.5 text-xs font-semibold text-rose-600 hover:bg-rose-50"
                                    @click="removeService(item)"
                                >
                                    <Trash2 class="h-3.5 w-3.5" />
                                    حذف
                                </button>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="!services.length">
                        <td
                            colspan="6"
                            class="px-3 py-8 text-center text-slate-400"
                        >
                            لا توجد خدمات بعد
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div
            v-if="showForm"
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4"
            @click.self="closeForm"
        >
            <div class="w-full max-w-lg rounded-3xl bg-white p-6 shadow-2xl">
                <h3 class="text-xl font-bold text-slate-900">{{ formTitle }}</h3>

                <form
                    class="mt-6 space-y-4"
                    @submit.prevent="submitForm"
                >
                    <div>
                        <label class="mb-1 block text-sm font-semibold text-slate-700">اسم الخدمة</label>
                        <input
                            v-model="form.label"
                            type="text"
                            required
                            class="w-full rounded-xl border border-slate-200 px-4 py-2.5 outline-none focus:border-indigo-500 focus:ring-4 focus:ring-indigo-100"
                            placeholder="مثال: تصوير مستندات"
                        >
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-semibold text-slate-700">النطاق</label>
                        <select
                            v-model="form.flow"
                            class="w-full rounded-xl border border-slate-200 px-4 py-2.5 outline-none focus:border-indigo-500 focus:ring-4 focus:ring-indigo-100"
                        >
                            <option value="both">الكل</option>
                            <option value="admission">طالب جديد</option>
                            <option value="current_student">طالب حالي</option>
                        </select>
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-semibold text-slate-700">الترتيب</label>
                        <input
                            v-model="form.sort_order"
                            type="number"
                            min="0"
                            class="w-full rounded-xl border border-slate-200 px-4 py-2.5 outline-none focus:border-indigo-500 focus:ring-4 focus:ring-indigo-100"
                            placeholder="اتركه فارغاً للإضافة في النهاية"
                        >
                        <p class="mt-1 text-xs text-slate-500">
                            رقم أصغر يظهر قبل الخدمات الأكبر. مثال: 25 بين دفع (20) وسحب ملف (30).
                        </p>
                    </div>

                    <label class="flex items-center gap-3 rounded-xl bg-slate-50 px-4 py-3">
                        <input
                            v-model="form.is_enabled"
                            type="checkbox"
                            class="h-4 w-4 rounded"
                        >
                        <span class="text-sm font-semibold text-slate-700">الخدمة مفعّلة</span>
                    </label>

                    <p
                        v-if="formError"
                        class="rounded-xl bg-red-50 px-4 py-3 text-sm text-red-700"
                    >
                        {{ formError }}
                    </p>

                    <div class="flex gap-3 pt-2">
                        <button
                            type="submit"
                            :disabled="saving"
                            class="flex-1 rounded-xl bg-indigo-600 py-3 font-bold text-white hover:bg-indigo-700 disabled:opacity-60"
                        >
                            {{ saving ? 'جاري الحفظ...' : 'حفظ' }}
                        </button>
                        <button
                            type="button"
                            class="rounded-xl border border-slate-200 px-4 py-3 font-semibold text-slate-700"
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
