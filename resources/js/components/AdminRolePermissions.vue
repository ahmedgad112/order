<script setup>
import { computed, onMounted, ref } from 'vue';
import { KeyRound, Plus, Save, Shield, Trash2 } from 'lucide-vue-next';
import { axios } from '../bootstrap';
import { useAuthStore } from '../stores/authStore';

const authStore = useAuthStore();

const matrix = ref([]);
const roles = ref([]);
const loading = ref(false);
const saving = ref(false);
const creating = ref(false);
const error = ref('');
const feedback = ref('');

const newRole = ref({
    name: '',
    serves_queue: false,
    rank: 20,
});

const permissionGroups = computed(() => {
    if (!matrix.value.length) {
        return [];
    }

    const groups = [];
    const seen = new Set();

    for (const item of matrix.value[0].permissions) {
        if (seen.has(item.group)) {
            continue;
        }

        seen.add(item.group);
        groups.push({
            key: item.group,
            label: item.group_label,
            permissions: matrix.value[0].permissions.filter((permission) => permission.group === item.group),
        });
    }

    return groups;
});

async function loadMatrix() {
    loading.value = true;
    error.value = '';

    try {
        const { data } = await axios.get('/admin/roles');
        roles.value = data.roles ?? [];
        matrix.value = data.matrix ?? [];
    } catch (err) {
        error.value = err.response?.data?.message ?? 'تعذر تحميل الأدوار والصلاحيات.';
        roles.value = [];
        matrix.value = [];
    } finally {
        loading.value = false;
    }
}

function permissionCell(roleRow, permissionValue) {
    return roleRow.permissions.find((item) => item.permission === permissionValue);
}

function togglePermission(roleRow, permissionValue) {
    const cell = permissionCell(roleRow, permissionValue);

    if (!cell || !cell.editable) {
        return;
    }

    cell.allowed = !cell.allowed;
}

async function save() {
    saving.value = true;
    error.value = '';
    feedback.value = '';

    const permissions = matrix.value.flatMap((roleRow) => (
        roleRow.permissions
            .filter((item) => item.editable)
            .map((item) => ({
                role: roleRow.role,
                permission: item.permission,
                allowed: Boolean(item.allowed),
            }))
    ));

    try {
        const { data } = await axios.put('/admin/role-permissions', { permissions });
        matrix.value = data.matrix ?? matrix.value;
        feedback.value = data.message ?? 'تم حفظ الصلاحيات.';
        await authStore.refreshUser();
    } catch (err) {
        const errors = err.response?.data?.errors ?? {};
        const firstKey = Object.keys(errors)[0];
        error.value = errors[firstKey]?.[0]
            ?? err.response?.data?.message
            ?? 'تعذر حفظ الصلاحيات.';
    } finally {
        saving.value = false;
    }
}

async function createRole() {
    creating.value = true;
    error.value = '';
    feedback.value = '';

    try {
        const { data } = await axios.post('/admin/roles', {
            name: newRole.value.name.trim(),
            serves_queue: Boolean(newRole.value.serves_queue),
            rank: Number(newRole.value.rank) || 20,
        });

        roles.value = (await axios.get('/admin/roles')).data.roles ?? roles.value;
        matrix.value = data.matrix ?? matrix.value;
        feedback.value = data.message ?? 'تم إنشاء الدور.';
        newRole.value = { name: '', serves_queue: false, rank: 20 };
        await authStore.refreshUser();
    } catch (err) {
        const errors = err.response?.data?.errors ?? {};
        const firstKey = Object.keys(errors)[0];
        error.value = errors[firstKey]?.[0]
            ?? err.response?.data?.message
            ?? 'تعذر إنشاء الدور.';
    } finally {
        creating.value = false;
    }
}

async function deleteRole(role) {
    if (role.is_system) {
        return;
    }

    if (!confirm(`هل تريد حذف الدور "${role.label}"؟`)) {
        return;
    }

    error.value = '';
    feedback.value = '';

    try {
        const { data } = await axios.delete(`/admin/roles/${role.value}`);
        matrix.value = data.matrix ?? [];
        roles.value = roles.value.filter((item) => item.value !== role.value);
        feedback.value = data.message ?? 'تم حذف الدور.';
    } catch (err) {
        const errors = err.response?.data?.errors ?? {};
        const firstKey = Object.keys(errors)[0];
        error.value = errors[firstKey]?.[0]
            ?? err.response?.data?.message
            ?? 'تعذر حذف الدور.';
    }
}

onMounted(() => {
    loadMatrix();
});
</script>

<template>
    <section class="space-y-4 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-6">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="min-w-0 space-y-1">
                <h2 class="flex items-center gap-2 text-base font-bold text-slate-900 sm:text-lg">
                    <KeyRound class="h-5 w-5 text-indigo-600" />
                    الأدوار والصلاحيات
                </h2>
                <p class="text-sm text-slate-500">
                    أنشئ أدواراً مخصصة وتحكم في الصفحات والأزرار الظاهرة لكل دور. صلاحيات السوبر أدمن مقفولة دائماً.
                </p>
            </div>
            <button
                type="button"
                class="inline-flex items-center gap-2 rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700 disabled:cursor-not-allowed disabled:opacity-60"
                :disabled="saving || loading || matrix.length === 0"
                @click="save"
            >
                <Save class="h-4 w-4" />
                {{ saving ? 'جارٍ الحفظ...' : 'حفظ الصلاحيات' }}
            </button>
        </div>

        <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
            <h3 class="mb-3 text-sm font-bold text-slate-800">إنشاء دور جديد</h3>
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <label class="space-y-1 text-sm">
                    <span class="font-medium text-slate-700">اسم الدور</span>
                    <input
                        v-model="newRole.name"
                        type="text"
                        class="w-full rounded-xl border border-slate-300 px-3 py-2"
                        placeholder="مثال: مشرف أرشيف"
                    >
                </label>
                <label class="space-y-1 text-sm">
                    <span class="font-medium text-slate-700">الرتبة</span>
                    <input
                        v-model.number="newRole.rank"
                        type="number"
                        min="1"
                        max="99"
                        class="w-full rounded-xl border border-slate-300 px-3 py-2"
                    >
                </label>
                <label class="flex items-end gap-2 pb-2 text-sm font-medium text-slate-700">
                    <input
                        v-model="newRole.serves_queue"
                        type="checkbox"
                        class="h-4 w-4 rounded border-slate-300 text-indigo-600"
                    >
                    يعمل كموظف شباك (مسارات/خطوات)
                </label>
                <div class="flex items-end">
                    <button
                        type="button"
                        class="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-emerald-700 disabled:opacity-60"
                        :disabled="creating || !newRole.name.trim()"
                        @click="createRole"
                    >
                        <Plus class="h-4 w-4" />
                        {{ creating ? 'جارٍ الإنشاء...' : 'إنشاء الدور' }}
                    </button>
                </div>
            </div>
        </div>

        <div
            v-if="roles.length"
            class="flex flex-wrap gap-2"
        >
            <div
                v-for="role in roles"
                :key="role.value"
                class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700"
            >
                <Shield class="h-3.5 w-3.5 text-slate-400" />
                {{ role.label }}
                <span class="text-slate-400">({{ role.users_count ?? 0 }})</span>
                <button
                    v-if="!role.is_system"
                    type="button"
                    class="text-rose-600 hover:text-rose-700"
                    title="حذف الدور"
                    @click="deleteRole(role)"
                >
                    <Trash2 class="h-3.5 w-3.5" />
                </button>
            </div>
        </div>

        <p
            v-if="feedback"
            class="rounded-xl bg-emerald-50 px-3 py-2 text-sm font-medium text-emerald-700"
        >
            {{ feedback }}
        </p>
        <p
            v-if="error"
            class="rounded-xl bg-rose-50 px-3 py-2 text-sm font-medium text-rose-700"
        >
            {{ error }}
        </p>

        <div
            v-if="loading"
            class="py-10 text-center text-sm text-slate-500"
        >
            جارٍ التحميل...
        </div>

        <div
            v-else-if="matrix.length"
            class="space-y-6"
        >
            <div
                v-for="group in permissionGroups"
                :key="group.key"
                class="overflow-x-auto rounded-xl border border-slate-200"
            >
                <div class="border-b border-slate-200 bg-slate-50 px-3 py-2 text-sm font-bold text-slate-800">
                    {{ group.label }}
                </div>
                <table class="min-w-full border-collapse text-sm">
                    <thead>
                        <tr class="border-b border-slate-200 text-start">
                            <th class="px-3 py-3 text-start font-bold text-slate-700">
                                الدور
                            </th>
                            <th
                                v-for="item in group.permissions"
                                :key="item.permission"
                                class="px-3 py-3 text-start font-bold text-slate-700"
                            >
                                {{ item.label }}
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="roleRow in matrix"
                            :key="`${group.key}-${roleRow.role}`"
                            class="border-b border-slate-100"
                        >
                            <td class="px-3 py-3">
                                <span class="inline-flex items-center gap-1.5 font-semibold text-slate-900">
                                    <Shield class="h-3.5 w-3.5 text-slate-400" />
                                    {{ roleRow.role_label }}
                                </span>
                            </td>
                            <td
                                v-for="item in group.permissions"
                                :key="`${roleRow.role}-${item.permission}`"
                                class="px-3 py-3"
                            >
                                <label
                                    class="inline-flex items-center gap-2"
                                    :class="permissionCell(roleRow, item.permission)?.editable ? 'cursor-pointer' : 'cursor-not-allowed opacity-70'"
                                >
                                    <input
                                        type="checkbox"
                                        class="h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
                                        :checked="permissionCell(roleRow, item.permission)?.allowed"
                                        :disabled="!permissionCell(roleRow, item.permission)?.editable || saving"
                                        @change="togglePermission(roleRow, item.permission)"
                                    >
                                    <span class="text-xs text-slate-500">
                                        {{
                                            permissionCell(roleRow, item.permission)?.editable
                                                ? (permissionCell(roleRow, item.permission)?.allowed ? 'مفعّل' : 'معطّل')
                                                : 'مقفول'
                                        }}
                                    </span>
                                </label>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </section>
</template>
