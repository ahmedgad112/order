<script setup>
import { computed, ref } from 'vue';
import { Pencil, Plus, Shield, Trash2, UserCog, Users, UserX } from 'lucide-vue-next';
import { useAuthStore } from '../stores/authStore';
import { useQueueStore } from '../stores/queueStore';
import PasswordInput from './PasswordInput.vue';
import QueueLaneSelect from './QueueLaneSelect.vue';

const authStore = useAuthStore();
const queueStore = useQueueStore();

const showForm = ref(false);
const editingUser = ref(null);
const formError = ref('');
const saving = ref(false);
const savingLaneUserId = ref(null);
const showBulkForm = ref(false);
const bulkError = ref('');
const bulkSaving = ref(false);
const bulkFeedback = ref('');

const emptyBulkForm = () => ({
    base_name: 'موظف',
    count: 5,
    counter_base: 'شباك',
    queue_lanes: [],
    process_steps: [],
});

const bulkForm = ref(emptyBulkForm());

const emptyForm = () => ({
    name: '',
    email: '',
    password: '',
    role: 'teller',
    counter_name: '',
    queue_lanes: [],
    process_steps: [],
    is_active: true,
});

const form = ref(emptyForm());

const roleLabel = {
    super_admin: 'سوبر أدمن',
    manager: 'مدير',
    teller: 'موظف',
};

const roleBadgeClass = {
    super_admin: 'bg-purple-100 text-purple-700',
    manager: 'bg-indigo-100 text-indigo-700',
    teller: 'bg-blue-100 text-blue-700',
};

const assignableRoles = computed(() => authStore.user?.assignable_roles ?? [
    { value: 'teller', label: 'موظف' },
]);

const canManageUser = (user) => {
    if (!user) {
        return false;
    }

    if (user.id === authStore.user?.id) {
        return true;
    }

    if (authStore.isSuperAdmin) {
        return true;
    }

    return authStore.isManager && user.role === 'teller';
};

const isEditing = computed(() => Boolean(editingUser.value));
const formTitle = computed(() => (isEditing.value ? 'تعديل مستخدم' : 'إضافة مستخدم جديد'));
const availableQueueLanes = computed(() => queueStore.queueLanes ?? []);
const availableProcessSteps = computed(() => queueStore.processSteps ?? []);

function laneValues(user) {
    return (user?.queue_lanes ?? []).map((lane) => lane.value ?? lane);
}

function processStepValues(user) {
    return (user?.process_steps ?? [])
        .filter((step) => step.enabled !== false)
        .map((step) => step.value ?? step);
}

function openCreate() {
    editingUser.value = null;
    form.value = {
        ...emptyForm(),
        queue_lanes: availableQueueLanes.value.map((lane) => lane.value),
        process_steps: availableProcessSteps.value.map((step) => step.value),
    };
    formError.value = '';
    showForm.value = true;
}

function openEdit(user) {
    editingUser.value = user;
    form.value = {
        name: user.name,
        email: user.email,
        password: '',
        role: user.role,
        counter_name: user.counter_name ?? '',
        queue_lanes: laneValues(user),
        process_steps: processStepValues(user),
        is_active: user.is_active,
    };
    formError.value = '';
    showForm.value = true;
}

function closeForm() {
    showForm.value = false;
    editingUser.value = null;
    form.value = emptyForm();
    formError.value = '';
}

async function submitForm() {
    saving.value = true;
    formError.value = '';

    const payload = {
        name: form.value.name.trim(),
        email: form.value.email.trim(),
        role: form.value.role,
        counter_name: form.value.role === 'teller' ? form.value.counter_name.trim() : null,
        queue_lanes: form.value.role === 'teller' ? form.value.queue_lanes : [],
        process_steps: form.value.role === 'teller' ? form.value.process_steps : [],
        is_active: form.value.is_active,
    };

    if (!isEditing.value || form.value.password) {
        payload.password = form.value.password;
    }

    try {
        if (isEditing.value) {
            await queueStore.updateUser(editingUser.value.id, payload);
        } else {
            await queueStore.createUser(payload);
        }
        closeForm();
    } catch (err) {
        const errors = err.response?.data?.errors;
        formError.value = errors
            ? Object.values(errors).flat()[0]
            : err.response?.data?.message ?? 'تعذر حفظ المستخدم.';
    } finally {
        saving.value = false;
    }
}

async function saveUserLanes(user, lanes) {
    if (!canManageUser(user) || user.role !== 'teller') {
        return;
    }

    savingLaneUserId.value = user.id;
    formError.value = '';

    try {
        await queueStore.updateUser(user.id, { queue_lanes: lanes });
    } catch (err) {
        const errors = err.response?.data?.errors;
        formError.value = errors
            ? Object.values(errors).flat()[0]
            : err.response?.data?.message ?? 'تعذر تحديث أنواع الطلب.';
    } finally {
        savingLaneUserId.value = null;
    }
}

async function saveUserProcessSteps(user, steps) {
    if (!canManageUser(user) || user.role !== 'teller') {
        return;
    }

    savingLaneUserId.value = user.id;
    formError.value = '';

    try {
        await queueStore.updateUser(user.id, { process_steps: steps });
    } catch (err) {
        const errors = err.response?.data?.errors;
        formError.value = errors
            ? Object.values(errors).flat()[0]
            : err.response?.data?.message ?? 'تعذر تحديث العمليات.';
    } finally {
        savingLaneUserId.value = null;
    }
}

function openBulkCreate() {
    bulkForm.value = {
        ...emptyBulkForm(),
        queue_lanes: availableQueueLanes.value.map((lane) => lane.value),
        process_steps: availableProcessSteps.value.map((step) => step.value),
    };
    bulkError.value = '';
    showBulkForm.value = true;
}

function closeBulkForm() {
    showBulkForm.value = false;
    bulkForm.value = emptyBulkForm();
    bulkError.value = '';
}

async function submitBulkForm() {
    bulkSaving.value = true;
    bulkError.value = '';
    bulkFeedback.value = '';

    try {
        const result = await queueStore.bulkCreateUsers({
            base_name: bulkForm.value.base_name.trim(),
            count: Number(bulkForm.value.count),
            counter_base: bulkForm.value.counter_base.trim(),
            queue_lanes: bulkForm.value.queue_lanes,
            process_steps: bulkForm.value.process_steps,
        });
        bulkFeedback.value = result.message ?? 'تم إنشاء المستخدمين بنجاح.';
        closeBulkForm();
    } catch (err) {
        const errors = err.response?.data?.errors;
        bulkError.value = errors
            ? Object.values(errors).flat()[0]
            : err.response?.data?.message ?? 'تعذر إنشاء المستخدمين.';
    } finally {
        bulkSaving.value = false;
    }
}

async function handleDeactivate(user) {
    if (!confirm(`هل تريد تعطيل المستخدم "${user.name}"؟`)) {
        return;
    }

    try {
        await queueStore.deactivateUser(user.id);
    } catch (err) {
        formError.value = err.response?.data?.message
            ?? err.response?.data?.errors?.user?.[0]
            ?? 'تعذر تعطيل المستخدم.';
    }
}

async function handleDelete(user) {
    if (!confirm(`هل تريد حذف المستخدم "${user.name}" نهائياً؟ لا يمكن التراجع عن هذا الإجراء.`)) {
        return;
    }

    try {
        await queueStore.deleteUser(user.id);
    } catch (err) {
        formError.value = err.response?.data?.message
            ?? err.response?.data?.errors?.user?.[0]
            ?? 'تعذر حذف المستخدم.';
    }
}
</script>

<template>
    <section class="rounded-3xl bg-white p-6 shadow-sm">
        <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="flex items-center gap-2 text-lg font-bold text-slate-800">
                    <UserCog class="h-5 w-5" />
                    إدارة المستخدمين
                </h2>
                <p class="text-sm text-slate-500">
                    {{ authStore.isSuperAdmin ? 'إنشاء وتعديل وحذف حسابات السوبر أدمن والمديرين والموظفين' : 'إنشاء وتعديل وحذف حسابات الموظفين' }}
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                <button
                    class="flex items-center justify-center gap-2 rounded-xl border border-indigo-200 bg-indigo-50 px-4 py-2.5 text-sm font-semibold text-indigo-700 hover:bg-indigo-100"
                    @click="openBulkCreate"
                >
                    <Users class="h-4 w-4" />
                    إضافة جماعية
                </button>
                <button
                    class="flex items-center justify-center gap-2 rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700"
                    @click="openCreate"
                >
                    <Plus class="h-4 w-4" />
                    إضافة مستخدم
                </button>
            </div>
        </div>

        <p v-if="bulkFeedback" class="mb-4 rounded-xl bg-green-50 px-4 py-3 text-sm text-green-700">
            {{ bulkFeedback }}
        </p>
        <p v-if="formError && !showForm" class="mb-4 rounded-xl bg-red-50 px-4 py-3 text-sm text-red-700">
            {{ formError }}
        </p>

        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
            <article
                v-for="user in queueStore.users"
                :key="user.id"
                class="relative z-0 overflow-visible rounded-2xl border border-slate-100 bg-slate-50/60 p-4 hover:z-20 focus-within:z-20"
            >
                <div class="mb-3 flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="font-semibold text-slate-800">{{ user.name }}</p>
                        <p class="mt-0.5 truncate text-sm text-slate-500">{{ user.email }}</p>
                    </div>
                    <span
                        class="shrink-0 rounded-full px-2 py-1 text-xs font-semibold"
                        :class="user.is_active ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'"
                    >
                        {{ user.is_active ? 'نشط' : 'معطّل' }}
                    </span>
                </div>
                <dl class="mb-4 space-y-2 text-sm">
                    <div class="flex items-center justify-between gap-3">
                        <dt class="text-slate-500">الدور</dt>
                        <dd>
                            <span
                                class="inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-xs font-semibold"
                                :class="roleBadgeClass[user.role] ?? 'bg-slate-100 text-slate-700'"
                            >
                                <Shield v-if="user.role === 'super_admin' || user.role === 'manager'" class="h-3 w-3" />
                                {{ user.role_label ?? roleLabel[user.role] ?? user.role }}
                            </span>
                        </dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-slate-500">الشباك</dt>
                        <dd class="text-slate-700">{{ user.counter_name ?? '—' }}</dd>
                    </div>
                    <div v-if="user.role === 'teller'" class="space-y-2">
                        <dt class="text-slate-500">أنواع الطلب</dt>
                        <dd>
                            <QueueLaneSelect
                                :model-value="laneValues(user)"
                                :options="availableQueueLanes"
                                :disabled="!canManageUser(user) || savingLaneUserId === user.id"
                                @update:model-value="saveUserLanes(user, $event)"
                            />
                        </dd>
                    </div>
                    <div v-if="user.role === 'teller'" class="space-y-2">
                        <dt class="text-slate-500">العمليات</dt>
                        <dd>
                            <QueueLaneSelect
                                :model-value="processStepValues(user)"
                                :options="availableProcessSteps"
                                placeholder="اختر العمليات"
                                empty-text="لا توجد عمليات متاحة"
                                summary-suffix="عمليات"
                                :disabled="!canManageUser(user) || savingLaneUserId === user.id"
                                @update:model-value="saveUserProcessSteps(user, $event)"
                            />
                        </dd>
                    </div>
                </dl>
                <div class="flex flex-wrap gap-2 border-t border-slate-200/80 pt-3">
                    <button
                        v-if="canManageUser(user)"
                        class="flex flex-1 items-center justify-center gap-2 rounded-xl border border-slate-200 px-3 py-2.5 text-sm font-semibold text-slate-700 hover:bg-white"
                        @click="openEdit(user)"
                    >
                        <Pencil class="h-4 w-4" />
                        تعديل
                    </button>
                    <button
                        v-if="user.id !== authStore.user?.id && user.is_active && canManageUser(user)"
                        class="flex flex-1 items-center justify-center gap-2 rounded-xl border border-amber-200 px-3 py-2.5 text-sm font-semibold text-amber-700 hover:bg-amber-50"
                        @click="handleDeactivate(user)"
                    >
                        <UserX class="h-4 w-4" />
                        تعطيل
                    </button>
                    <button
                        v-if="user.id !== authStore.user?.id && canManageUser(user)"
                        class="flex flex-1 items-center justify-center gap-2 rounded-xl border border-red-200 px-3 py-2.5 text-sm font-semibold text-red-600 hover:bg-red-50"
                        @click="handleDelete(user)"
                    >
                        <Trash2 class="h-4 w-4" />
                        حذف
                    </button>
                </div>
            </article>
            <p v-if="!queueStore.users.length" class="col-span-full py-12 text-center text-slate-400">
                لا يوجد مستخدمون
            </p>
        </div>

        <div
            v-if="showForm"
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4"
            @click.self="closeForm"
        >
            <div class="w-full max-w-lg rounded-3xl bg-white p-6 shadow-2xl">
                <h3 class="text-xl font-bold text-slate-900">{{ formTitle }}</h3>

                <form class="mt-6 space-y-4" @submit.prevent="submitForm">
                    <div>
                        <label class="mb-1 block text-sm font-semibold text-slate-700">الاسم</label>
                        <input
                            v-model="form.name"
                            type="text"
                            required
                            class="w-full rounded-xl border border-slate-200 px-4 py-2.5 outline-none focus:border-indigo-500 focus:ring-4 focus:ring-indigo-100"
                        />
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-semibold text-slate-700">البريد الإلكتروني</label>
                        <input
                            v-model="form.email"
                            type="email"
                            required
                            class="w-full rounded-xl border border-slate-200 px-4 py-2.5 outline-none focus:border-indigo-500 focus:ring-4 focus:ring-indigo-100"
                        />
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-semibold text-slate-700">
                            {{ isEditing ? 'كلمة مرور جديدة (اختياري)' : 'كلمة المرور' }}
                        </label>
                        <PasswordInput
                            v-model="form.password"
                            :required="!isEditing"
                            minlength="8"
                            autocomplete="new-password"
                            input-class="w-full rounded-xl border border-slate-200 px-4 py-2.5 outline-none focus:border-indigo-500 focus:ring-4 focus:ring-indigo-100"
                        />
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-semibold text-slate-700">الدور</label>
                        <select
                            v-model="form.role"
                            class="w-full rounded-xl border border-slate-200 px-4 py-2.5 outline-none focus:border-indigo-500 focus:ring-4 focus:ring-indigo-100"
                            :disabled="editingUser?.id === authStore.user?.id"
                        >
                            <option
                                v-for="role in assignableRoles"
                                :key="role.value"
                                :value="role.value"
                            >
                                {{ role.label }}
                            </option>
                        </select>
                    </div>

                    <div v-if="form.role === 'teller'">
                        <label class="mb-1 block text-sm font-semibold text-slate-700">اسم الشباك</label>
                        <input
                            v-model="form.counter_name"
                            type="text"
                            required
                            class="w-full rounded-xl border border-slate-200 px-4 py-2.5 outline-none focus:border-indigo-500 focus:ring-4 focus:ring-indigo-100"
                            placeholder="مثال: شباك 3"
                        />
                    </div>

                    <div v-if="form.role === 'teller'" class="space-y-2">
                        <label class="block text-sm font-semibold text-slate-700">أنواع الطلب المخصصة</label>
                        <QueueLaneSelect v-model="form.queue_lanes" :options="availableQueueLanes" />
                        <p class="text-xs text-slate-500">يمكن اختيار أكثر من نوع من القائمة.</p>
                    </div>

                    <div v-if="form.role === 'teller'" class="space-y-2">
                        <label class="block text-sm font-semibold text-slate-700">العمليات المخصصة</label>
                        <QueueLaneSelect
                            v-model="form.process_steps"
                            :options="availableProcessSteps"
                            placeholder="اختر العمليات"
                            empty-text="لا توجد عمليات متاحة"
                            summary-suffix="عمليات"
                        />
                        <p class="text-xs text-slate-500">الموظف يشوف أزرار العمليات المختارة فقط.</p>
                    </div>

                    <label class="flex items-center gap-3 rounded-xl bg-slate-50 px-4 py-3">
                        <input v-model="form.is_active" type="checkbox" class="h-4 w-4 rounded" />
                        <span class="text-sm font-semibold text-slate-700">الحساب نشط</span>
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
                            {{ saving ? 'جاري الحفظ...' : (isEditing ? 'حفظ التعديلات' : 'إنشاء المستخدم') }}
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

        <div
            v-if="showBulkForm"
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4"
            @click.self="closeBulkForm"
        >
            <div class="w-full max-w-lg rounded-3xl bg-white p-6 shadow-2xl">
                <h3 class="text-xl font-bold text-slate-900">إضافة موظفين دفعة واحدة</h3>
                <p class="mt-1 text-sm text-slate-500">
                    سيتم إنشاء حسابات موظفين بكلمة مرور موحدة: <span class="font-bold text-slate-700">123456789</span>
                </p>

                <form class="mt-6 space-y-4" @submit.prevent="submitBulkForm">
                    <div>
                        <label class="mb-1 block text-sm font-semibold text-slate-700">الاسم الأساسي</label>
                        <input
                            v-model="bulkForm.base_name"
                            type="text"
                            required
                            class="w-full rounded-xl border border-slate-200 px-4 py-2.5 outline-none focus:border-indigo-500 focus:ring-4 focus:ring-indigo-100"
                            placeholder="موظف"
                        />
                        <p class="mt-1 text-xs text-slate-500">سيصبح: {{ bulkForm.base_name.trim() || 'موظف' }} 1، {{ bulkForm.base_name.trim() || 'موظف' }} 2، ...</p>
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-semibold text-slate-700">عدد المستخدمين</label>
                        <input
                            v-model.number="bulkForm.count"
                            type="number"
                            required
                            min="1"
                            max="50"
                            class="w-full rounded-xl border border-slate-200 px-4 py-2.5 outline-none focus:border-indigo-500 focus:ring-4 focus:ring-indigo-100"
                        />
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-semibold text-slate-700">اسم الشباك الأساسي</label>
                        <input
                            v-model="bulkForm.counter_base"
                            type="text"
                            required
                            class="w-full rounded-xl border border-slate-200 px-4 py-2.5 outline-none focus:border-indigo-500 focus:ring-4 focus:ring-indigo-100"
                            placeholder="شباك"
                        />
                        <p class="mt-1 text-xs text-slate-500">سيصبح: {{ bulkForm.counter_base.trim() || 'شباك' }} 1، {{ bulkForm.counter_base.trim() || 'شباك' }} 2، ...</p>
                    </div>

                    <div class="space-y-2">
                        <label class="block text-sm font-semibold text-slate-700">أنواع الطلب المخصصة</label>
                        <QueueLaneSelect v-model="bulkForm.queue_lanes" :options="availableQueueLanes" />
                        <p class="text-xs text-slate-500">تنطبق على جميع الموظفين المنشأين.</p>
                    </div>

                    <div class="space-y-2">
                        <label class="block text-sm font-semibold text-slate-700">العمليات المخصصة</label>
                        <QueueLaneSelect
                            v-model="bulkForm.process_steps"
                            :options="availableProcessSteps"
                            placeholder="اختر العمليات"
                            empty-text="لا توجد عمليات متاحة"
                            summary-suffix="عمليات"
                        />
                        <p class="text-xs text-slate-500">تنطبق على جميع الموظفين المنشأين.</p>
                    </div>

                    <p v-if="bulkError" class="rounded-xl bg-red-50 px-4 py-3 text-sm text-red-700">
                        {{ bulkError }}
                    </p>

                    <div class="flex gap-3 pt-2">
                        <button
                            type="submit"
                            :disabled="bulkSaving"
                            class="flex-1 rounded-xl bg-indigo-600 py-3 font-bold text-white hover:bg-indigo-700 disabled:opacity-60"
                        >
                            {{ bulkSaving ? 'جاري الإنشاء...' : `إنشاء ${bulkForm.count || ''} موظف` }}
                        </button>
                        <button
                            type="button"
                            class="flex-1 rounded-xl border border-slate-200 py-3 font-semibold text-slate-700 hover:bg-slate-50"
                            @click="closeBulkForm"
                        >
                            إلغاء
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </section>
</template>
