<script setup>
import { ref } from 'vue';
import { KeyRound } from 'lucide-vue-next';
import { useAuthStore } from '../stores/authStore';
import PasswordInput from './PasswordInput.vue';

const authStore = useAuthStore();

const showForm = ref(false);
const saving = ref(false);
const error = ref('');
const success = ref('');

const emptyForm = () => ({
    current_password: '',
    password: '',
    password_confirmation: '',
});

const form = ref(emptyForm());

const fieldClass = 'w-full rounded-xl border border-slate-200 px-4 py-2.5 outline-none focus:border-indigo-500 focus:ring-4 focus:ring-indigo-100';

function openForm() {
    form.value = emptyForm();
    error.value = '';
    success.value = '';
    showForm.value = true;
}

function closeForm() {
    showForm.value = false;
    form.value = emptyForm();
    error.value = '';
    success.value = '';
}

async function submit() {
    saving.value = true;
    error.value = '';
    success.value = '';

    try {
        const message = await authStore.changePassword(form.value);
        success.value = message;
        form.value = emptyForm();
    } catch (err) {
        const errors = err.response?.data?.errors;
        error.value = errors
            ? Object.values(errors).flat()[0]
            : err.response?.data?.message ?? 'تعذر تغيير كلمة المرور.';
    } finally {
        saving.value = false;
    }
}
</script>

<template>
    <button
        type="button"
        class="flex w-fit items-center gap-2 rounded-xl border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50"
        @click="openForm"
    >
        <KeyRound class="h-4 w-4" />
        تغيير كلمة المرور
    </button>

    <div
        v-if="showForm"
        class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4"
        @click.self="closeForm"
    >
        <div class="w-full max-w-md rounded-3xl bg-white p-6 shadow-2xl">
            <h3 class="text-xl font-bold text-slate-900">تغيير كلمة المرور</h3>
            <p class="mt-1 text-sm text-slate-500">يمكنك تغيير كلمة مرور حسابك في أي وقت.</p>

            <form class="mt-6 space-y-4" @submit.prevent="submit">
                <div>
                    <label class="mb-1 block text-sm font-semibold text-slate-700">كلمة المرور الحالية</label>
                    <PasswordInput
                        v-model="form.current_password"
                        required
                        autocomplete="current-password"
                        :input-class="fieldClass"
                    />
                </div>

                <div>
                    <label class="mb-1 block text-sm font-semibold text-slate-700">كلمة المرور الجديدة</label>
                    <PasswordInput
                        v-model="form.password"
                        required
                        minlength="8"
                        autocomplete="new-password"
                        :input-class="fieldClass"
                    />
                </div>

                <div>
                    <label class="mb-1 block text-sm font-semibold text-slate-700">تأكيد كلمة المرور الجديدة</label>
                    <PasswordInput
                        v-model="form.password_confirmation"
                        required
                        minlength="8"
                        autocomplete="new-password"
                        :input-class="fieldClass"
                    />
                </div>

                <p v-if="error" class="rounded-xl bg-red-50 px-4 py-3 text-sm text-red-700">
                    {{ error }}
                </p>
                <p v-if="success" class="rounded-xl bg-green-50 px-4 py-3 text-sm text-green-700">
                    {{ success }}
                </p>

                <div class="flex gap-3 pt-2">
                    <button
                        type="submit"
                        :disabled="saving"
                        class="flex-1 rounded-xl bg-indigo-600 py-3 font-bold text-white hover:bg-indigo-700 disabled:opacity-60"
                    >
                        {{ saving ? 'جاري الحفظ...' : 'حفظ كلمة المرور' }}
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
</template>
