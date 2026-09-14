<script setup>
import { computed, ref } from 'vue';
import { Building2, EyeOff, GraduationCap, Pencil, Plus } from 'lucide-vue-next';
import { useAuthStore } from '../stores/authStore';
import { useQueueStore } from '../stores/queueStore';

const authStore = useAuthStore();
const queueStore = useQueueStore();

const kind = ref('faculty');
const showForm = ref(false);
const editingItem = ref(null);
const formError = ref('');
const saving = ref(false);
const feedback = ref('');

const emptyFacultyForm = () => ({
    name: '',
    seat_number_min_digits: 7,
    seat_number_max_digits: 7,
    is_active: true,
});

const emptyCollegeForm = () => ({
    name: '',
    is_active: true,
});

const form = ref(emptyFacultyForm());

const isEditing = computed(() => Boolean(editingItem.value));
const canManage = computed(() => authStore.canControlSystem);
const faculties = computed(() => queueStore.catalogFaculties ?? []);
const colleges = computed(() => queueStore.catalogColleges ?? []);
const formTitle = computed(() => {
    if (kind.value === 'faculty') {
        return isEditing.value ? 'تعديل كلية الطلاب الحاليين' : 'إضافة كلية للطلاب الحاليين';
    }

    return isEditing.value ? 'تعديل كلية بطاقة الترشيح' : 'إضافة كلية لبطاقة الترشيح';
});

function openCreate(nextKind) {
    kind.value = nextKind;
    editingItem.value = null;
    form.value = nextKind === 'faculty' ? emptyFacultyForm() : emptyCollegeForm();
    formError.value = '';
    showForm.value = true;
}

function openEdit(nextKind, item) {
    kind.value = nextKind;
    editingItem.value = item;
    form.value = nextKind === 'faculty'
        ? {
            name: item.label,
            seat_number_min_digits: item.seat_number_min_digits ?? 7,
            seat_number_max_digits: item.seat_number_max_digits ?? 7,
            is_active: item.is_active !== false,
        }
        : {
            name: item.label,
            is_active: item.is_active !== false,
        };
    formError.value = '';
    showForm.value = true;
}

function closeForm() {
    showForm.value = false;
    editingItem.value = null;
    formError.value = '';
}

function firstError(err) {
    const errors = err.response?.data?.errors ?? {};
    const firstKey = Object.keys(errors)[0];

    return err.response?.data?.message
        ?? errors[firstKey]?.[0]
        ?? 'تعذر حفظ الكلية.';
}

async function submitForm() {
    saving.value = true;
    formError.value = '';
    feedback.value = '';

    const payload = kind.value === 'faculty'
        ? {
            name: form.value.name.trim(),
            seat_number_min_digits: Number(form.value.seat_number_min_digits),
            seat_number_max_digits: Number(form.value.seat_number_max_digits),
            is_active: form.value.is_active,
        }
        : {
            name: form.value.name.trim(),
            is_active: form.value.is_active,
        };

    try {
        const result = isEditing.value
            ? (kind.value === 'faculty'
                ? await queueStore.updateFaculty(editingItem.value.id, payload)
                : await queueStore.updateCollege(editingItem.value.id, payload))
            : (kind.value === 'faculty'
                ? await queueStore.createFaculty(payload)
                : await queueStore.createCollege(payload));

        feedback.value = result.message;
        closeForm();
    } catch (err) {
        formError.value = firstError(err);
    } finally {
        saving.value = false;
    }
}

async function handleDeactivate(nextKind, item) {
    if (!confirm(`هل تريد إخفاء "${item.label}" من شاشات التسجيل؟`)) {
        return;
    }

    formError.value = '';
    feedback.value = '';

    try {
        const result = nextKind === 'faculty'
            ? await queueStore.deactivateFaculty(item.id)
            : await queueStore.deactivateCollege(item.id);
        feedback.value = result.message;
    } catch (err) {
        formError.value = firstError(err);
    }
}
</script>

<template>
    <section class="rounded-3xl bg-white p-6 shadow-sm">
        <div class="mb-6">
            <h2 class="flex items-center gap-2 text-lg font-bold text-slate-800">
                <GraduationCap class="h-5 w-5" />
                إدارة الكليات
            </h2>
            <p class="mt-1 text-sm text-slate-500">
                أضف كليات الطلاب الحاليين وكليات بطاقة الترشيح من هنا بدون تعديل الكود.
            </p>
        </div>

        <p v-if="feedback" class="mb-4 text-sm font-semibold text-green-700">{{ feedback }}</p>
        <p v-if="formError && !showForm" class="mb-4 rounded-xl bg-red-50 px-4 py-3 text-sm text-red-700">
            {{ formError }}
        </p>

        <div class="grid gap-6 lg:grid-cols-2">
            <div>
                <div class="mb-3 flex items-center justify-between gap-3">
                    <h3 class="flex items-center gap-2 font-bold text-slate-800">
                        <Building2 class="h-4 w-4 text-indigo-600" />
                        كليات الطلاب الحاليين
                    </h3>
                    <button
                        v-if="canManage"
                        class="flex items-center gap-1 rounded-xl bg-indigo-600 px-3 py-2 text-xs font-semibold text-white hover:bg-indigo-700"
                        @click="openCreate('faculty')"
                    >
                        <Plus class="h-3.5 w-3.5" />
                        إضافة
                    </button>
                </div>
                <div class="space-y-2">
                    <article
                        v-for="faculty in faculties"
                        :key="faculty.id"
                        class="flex items-start justify-between gap-3 rounded-2xl border px-4 py-3"
                        :class="faculty.is_active ? 'border-indigo-100 bg-indigo-50/60' : 'border-slate-200 bg-slate-50'"
                    >
                        <div>
                            <p class="font-semibold text-slate-800">{{ faculty.label }}</p>
                            <p class="mt-1 text-xs text-slate-500">
                                رقم الجلوس:
                                {{ faculty.seat_number_min_digits === faculty.seat_number_max_digits
                                    ? `${faculty.seat_number_min_digits} أرقام`
                                    : `${faculty.seat_number_min_digits} إلى ${faculty.seat_number_max_digits} أرقام` }}
                            </p>
                            <span
                                class="mt-2 inline-flex rounded-full px-2 py-0.5 text-[11px] font-bold"
                                :class="faculty.is_active ? 'bg-green-100 text-green-700' : 'bg-slate-200 text-slate-600'"
                            >
                                {{ faculty.is_active ? 'ظاهرة' : 'مخفية' }}
                            </span>
                        </div>
                        <div v-if="canManage" class="flex shrink-0 gap-2">
                            <button
                                class="rounded-xl border border-slate-200 bg-white p-2 text-slate-600 hover:bg-slate-50"
                                @click="openEdit('faculty', faculty)"
                            >
                                <Pencil class="h-4 w-4" />
                            </button>
                            <button
                                v-if="faculty.is_active"
                                class="rounded-xl border border-red-200 bg-white p-2 text-red-600 hover:bg-red-50"
                                @click="handleDeactivate('faculty', faculty)"
                            >
                                <EyeOff class="h-4 w-4" />
                            </button>
                        </div>
                    </article>
                    <p v-if="!faculties.length" class="py-6 text-center text-sm text-slate-400">لا توجد كليات</p>
                </div>
            </div>

            <div>
                <div class="mb-3 flex items-center justify-between gap-3">
                    <h3 class="flex items-center gap-2 font-bold text-slate-800">
                        <Building2 class="h-4 w-4 text-indigo-600" />
                        كليات بطاقة الترشيح
                    </h3>
                    <button
                        v-if="canManage"
                        class="flex items-center gap-1 rounded-xl bg-indigo-600 px-3 py-2 text-xs font-semibold text-white hover:bg-indigo-700"
                        @click="openCreate('college')"
                    >
                        <Plus class="h-3.5 w-3.5" />
                        إضافة
                    </button>
                </div>
                <div class="space-y-2">
                    <article
                        v-for="college in colleges"
                        :key="college.id"
                        class="flex items-start justify-between gap-3 rounded-2xl border px-4 py-3"
                        :class="college.is_active ? 'border-indigo-100 bg-indigo-50/60' : 'border-slate-200 bg-slate-50'"
                    >
                        <div>
                            <p class="font-semibold text-slate-800">{{ college.label }}</p>
                            <span
                                class="mt-2 inline-flex rounded-full px-2 py-0.5 text-[11px] font-bold"
                                :class="college.is_active ? 'bg-green-100 text-green-700' : 'bg-slate-200 text-slate-600'"
                            >
                                {{ college.is_active ? 'ظاهرة' : 'مخفية' }}
                            </span>
                        </div>
                        <div v-if="canManage" class="flex shrink-0 gap-2">
                            <button
                                class="rounded-xl border border-slate-200 bg-white p-2 text-slate-600 hover:bg-slate-50"
                                @click="openEdit('college', college)"
                            >
                                <Pencil class="h-4 w-4" />
                            </button>
                            <button
                                v-if="college.is_active"
                                class="rounded-xl border border-red-200 bg-white p-2 text-red-600 hover:bg-red-50"
                                @click="handleDeactivate('college', college)"
                            >
                                <EyeOff class="h-4 w-4" />
                            </button>
                        </div>
                    </article>
                    <p v-if="!colleges.length" class="py-6 text-center text-sm text-slate-400">لا توجد كليات</p>
                </div>
            </div>
        </div>

        <p v-if="!canManage" class="mt-4 text-sm font-semibold text-slate-500">
            إضافة الكليات وتعديلها متاح للسوبر أدمن فقط.
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
                        <label class="mb-1 block text-sm font-semibold text-slate-700">اسم الكلية</label>
                        <input
                            v-model="form.name"
                            type="text"
                            required
                            minlength="2"
                            class="w-full rounded-xl border border-slate-200 px-4 py-2.5 outline-none focus:border-indigo-500 focus:ring-4 focus:ring-indigo-100"
                            placeholder="مثال: صناعة وطاقة"
                        />
                    </div>

                    <div v-if="kind === 'faculty'" class="grid gap-3 sm:grid-cols-2">
                        <div>
                            <label class="mb-1 block text-sm font-semibold text-slate-700">أقل عدد لأرقام الجلوس</label>
                            <input
                                v-model.number="form.seat_number_min_digits"
                                type="number"
                                min="1"
                                max="20"
                                required
                                class="w-full rounded-xl border border-slate-200 px-4 py-2.5 outline-none focus:border-indigo-500 focus:ring-4 focus:ring-indigo-100"
                            />
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-semibold text-slate-700">أكبر عدد لأرقام الجلوس</label>
                            <input
                                v-model.number="form.seat_number_max_digits"
                                type="number"
                                min="1"
                                max="20"
                                required
                                class="w-full rounded-xl border border-slate-200 px-4 py-2.5 outline-none focus:border-indigo-500 focus:ring-4 focus:ring-indigo-100"
                            />
                        </div>
                    </div>

                    <label class="flex items-center gap-3 rounded-xl bg-slate-50 px-4 py-3">
                        <input v-model="form.is_active" type="checkbox" class="h-4 w-4 rounded accent-indigo-600" />
                        <span class="text-sm font-semibold text-slate-700">ظاهرة في شاشات التسجيل</span>
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
                            {{ saving ? 'جاري الحفظ...' : (isEditing ? 'حفظ التعديلات' : 'إضافة الكلية') }}
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
