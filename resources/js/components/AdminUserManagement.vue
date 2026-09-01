<script setup>
import { computed, ref } from 'vue';
import { Pencil, Plus, Shield, UserCog, UserX } from 'lucide-vue-next';
import { useAuthStore } from '../stores/authStore';
import { useQueueStore } from '../stores/queueStore';
import PasswordInput from './PasswordInput.vue';

const authStore = useAuthStore();
const queueStore = useQueueStore();

const showForm = ref(false);
const editingUser = ref(null);
const formError = ref('');
const saving = ref(false);

const emptyForm = () => ({
    name: '',
    email: '',
    password: '',
    role: 'teller',
    counter_name: '',
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

function openCreate() {
    editingUser.value = null;
    form.value = emptyForm();
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
                    {{ authStore.isSuperAdmin ? 'إنشاء وتعديل حسابات السوبر أدمن والمديرين والموظفين' : 'إنشاء وتعديل حسابات الموظفين' }}
                </p>
            </div>
            <button
                class="flex items-center justify-center gap-2 rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700"
                @click="openCreate"
            >
                <Plus class="h-4 w-4" />
                إضافة مستخدم
            </button>
        </div>

        <p v-if="formError && !showForm" class="mb-4 rounded-xl bg-red-50 px-4 py-3 text-sm text-red-700">
            {{ formError }}
        </p>

        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
            <article
                v-for="user in queueStore.users"
                :key="user.id"
                class="rounded-2xl border border-slate-100 bg-slate-50/60 p-4"
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
                </dl>
                <div class="flex gap-2 border-t border-slate-200/80 pt-3">
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
                        class="flex flex-1 items-center justify-center gap-2 rounded-xl border border-red-200 px-3 py-2.5 text-sm font-semibold text-red-600 hover:bg-red-50"
                        @click="handleDeactivate(user)"
                    >
                        <UserX class="h-4 w-4" />
                        تعطيل
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
            <div class="max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-3xl bg-white p-6 shadow-2xl">
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
    </section>
</template>
