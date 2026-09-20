<script setup>
import { onMounted, ref } from 'vue';
import { KeyRound, Save, Shield } from 'lucide-vue-next';
import { axios } from '../bootstrap';
import { useAuthStore } from '../stores/authStore';

const authStore = useAuthStore();

const matrix = ref([]);
const loading = ref(false);
const saving = ref(false);
const error = ref('');
const feedback = ref('');

async function loadMatrix() {
    loading.value = true;
    error.value = '';

    try {
        const { data } = await axios.get('/admin/role-permissions');
        matrix.value = data.matrix ?? [];
    } catch (err) {
        error.value = err.response?.data?.message ?? 'تعذر تحميل صلاحيات الأدوار.';
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
                    صلاحيات الأزرار حسب الدور
                </h2>
                <p class="text-sm text-slate-500">
                    تحكم في الأزرار التي تظهر لكل دور. صلاحيات السوبر أدمن مقفولة دائماً.
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
            class="overflow-x-auto"
        >
            <table class="min-w-full border-collapse text-sm">
                <thead>
                    <tr class="border-b border-slate-200 text-start">
                        <th class="px-3 py-3 text-start font-bold text-slate-700">
                            الدور
                        </th>
                        <th
                            v-for="item in matrix[0].permissions"
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
                        :key="roleRow.role"
                        class="border-b border-slate-100"
                    >
                        <td class="px-3 py-3">
                            <span class="inline-flex items-center gap-1.5 font-semibold text-slate-900">
                                <Shield class="h-3.5 w-3.5 text-slate-400" />
                                {{ roleRow.role_label }}
                            </span>
                        </td>
                        <td
                            v-for="item in roleRow.permissions"
                            :key="`${roleRow.role}-${item.permission}`"
                            class="px-3 py-3"
                        >
                            <label
                                class="inline-flex items-center gap-2"
                                :class="item.editable ? 'cursor-pointer' : 'cursor-not-allowed opacity-70'"
                            >
                                <input
                                    type="checkbox"
                                    class="h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
                                    :checked="item.allowed"
                                    :disabled="!item.editable || saving"
                                    @change="togglePermission(roleRow, item.permission)"
                                >
                                <span class="text-xs text-slate-500">
                                    {{ item.editable ? (item.allowed ? 'مفعّل' : 'معطّل') : 'مقفول' }}
                                </span>
                            </label>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </section>
</template>
