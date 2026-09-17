<script setup>
import { ref } from 'vue';
import { Settings } from 'lucide-vue-next';
import { useAuthStore } from '../stores/authStore';
import PasswordInput from './PasswordInput.vue';

defineProps({
    block: {
        type: Boolean,
        default: false,
    },
});

const authStore = useAuthStore();

const showForm = ref(false);
const profileSaving = ref(false);
const profileError = ref('');
const profileSuccess = ref('');
const passwordSaving = ref(false);
const passwordError = ref('');
const passwordSuccess = ref('');

const emptyPasswordForm = () => ({
    current_password: '',
    password: '',
    password_confirmation: '',
});

const profileForm = ref({ name: '', email: '', current_password: '' });
const passwordForm = ref(emptyPasswordForm());

const fieldClass = 'w-full rounded-xl border border-slate-200 px-4 py-2.5 outline-none focus:border-indigo-500 focus:ring-4 focus:ring-indigo-100';

function firstError(err, fallback) {
    const errors = err.response?.data?.errors;

    return errors
        ? Object.values(errors).flat()[0]
        : err.response?.data?.message ?? fallback;
}

function openForm() {
    profileForm.value = {
        name: authStore.user?.name ?? '',
        email: authStore.user?.email ?? '',
        current_password: '',
    };
    passwordForm.value = emptyPasswordForm();
    profileError.value = '';
    profileSuccess.value = '';
    passwordError.value = '';
    passwordSuccess.value = '';
    showForm.value = true;
}

function closeForm() {
    showForm.value = false;
}

async function submitProfile() {
    profileSaving.value = true;
    profileError.value = '';
    profileSuccess.value = '';

    try {
        profileSuccess.value = await authStore.updateProfile({
            name: profileForm.value.name.trim(),
            email: profileForm.value.email.trim(),
            current_password: profileForm.value.current_password,
        });
        profileForm.value.current_password = '';
    } catch (err) {
        profileError.value = firstError(err, 'تعذر تحديث بيانات الحساب.');
    } finally {
        profileSaving.value = false;
    }
}

async function submitPassword() {
    passwordSaving.value = true;
    passwordError.value = '';
    passwordSuccess.value = '';

    try {
        passwordSuccess.value = await authStore.changePassword(passwordForm.value);
        passwordForm.value = emptyPasswordForm();
    } catch (err) {
        passwordError.value = firstError(err, 'تعذر تغيير كلمة المرور.');
    } finally {
        passwordSaving.value = false;
    }
}
</script>

<template>
    <button
        type="button"
        class="flex items-center gap-2 rounded-xl border border-slate-200 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50"
        :class="block ? 'w-full justify-center' : 'w-fit'"
        @click="openForm"
    >
        <Settings class="h-4 w-4" />
        <span :class="block ? '' : 'hidden sm:inline'">إعدادات الحساب</span>
    </button>

    <Teleport to="body">
        <div
            v-if="showForm"
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4"
            @click.self="closeForm"
        >
            <div class="max-h-[90vh] w-full max-w-md overflow-y-auto rounded-3xl bg-white p-6 shadow-2xl">
            <h3 class="text-xl font-bold text-slate-900">إعدادات الحساب</h3>
            <p class="mt-1 text-sm text-slate-500">يمكنك تحديث اسمك وبريدك الإلكتروني أو تغيير كلمة المرور.</p>

            <form class="mt-6 space-y-4" @submit.prevent="submitProfile">
                <h4 class="text-sm font-bold text-slate-700">بيانات الحساب</h4>

                <div>
                    <label class="mb-1 block text-sm font-semibold text-slate-700">الاسم</label>
                    <input
                        v-model="profileForm.name"
                        type="text"
                        required
                        minlength="3"
                        :class="fieldClass"
                    />
                </div>

                <div>
                    <label class="mb-1 block text-sm font-semibold text-slate-700">البريد الإلكتروني</label>
                    <input
                        v-model="profileForm.email"
                        type="email"
                        required
                        :class="fieldClass"
                    />
                </div>

                <div>
                    <label class="mb-1 block text-sm font-semibold text-slate-700">كلمة المرور الحالية للتأكيد</label>
                    <PasswordInput
                        v-model="profileForm.current_password"
                        required
                        autocomplete="current-password"
                        :input-class="fieldClass"
                    />
                </div>

                <p v-if="profileError" class="rounded-xl bg-red-50 px-4 py-3 text-sm text-red-700">
                    {{ profileError }}
                </p>
                <p v-if="profileSuccess" class="rounded-xl bg-green-50 px-4 py-3 text-sm text-green-700">
                    {{ profileSuccess }}
                </p>

                <button
                    type="submit"
                    :disabled="profileSaving"
                    class="w-full rounded-xl bg-indigo-600 py-3 font-bold text-white hover:bg-indigo-700 disabled:opacity-60"
                >
                    {{ profileSaving ? 'جاري الحفظ...' : 'حفظ البيانات' }}
                </button>
            </form>

            <hr class="my-6 border-slate-200" />

            <form class="space-y-4" @submit.prevent="submitPassword">
                <h4 class="text-sm font-bold text-slate-700">تغيير كلمة المرور</h4>

                <div>
                    <label class="mb-1 block text-sm font-semibold text-slate-700">كلمة المرور الحالية</label>
                    <PasswordInput
                        v-model="passwordForm.current_password"
                        required
                        autocomplete="current-password"
                        :input-class="fieldClass"
                    />
                </div>

                <div>
                    <label class="mb-1 block text-sm font-semibold text-slate-700">كلمة المرور الجديدة</label>
                    <PasswordInput
                        v-model="passwordForm.password"
                        required
                        minlength="8"
                        autocomplete="new-password"
                        :input-class="fieldClass"
                    />
                </div>

                <div>
                    <label class="mb-1 block text-sm font-semibold text-slate-700">تأكيد كلمة المرور الجديدة</label>
                    <PasswordInput
                        v-model="passwordForm.password_confirmation"
                        required
                        minlength="8"
                        autocomplete="new-password"
                        :input-class="fieldClass"
                    />
                </div>

                <p v-if="passwordError" class="rounded-xl bg-red-50 px-4 py-3 text-sm text-red-700">
                    {{ passwordError }}
                </p>
                <p v-if="passwordSuccess" class="rounded-xl bg-green-50 px-4 py-3 text-sm text-green-700">
                    {{ passwordSuccess }}
                </p>

                <button
                    type="submit"
                    :disabled="passwordSaving"
                    class="w-full rounded-xl bg-indigo-600 py-3 font-bold text-white hover:bg-indigo-700 disabled:opacity-60"
                >
                    {{ passwordSaving ? 'جاري الحفظ...' : 'حفظ كلمة المرور' }}
                </button>
            </form>

            <button
                type="button"
                class="mt-6 w-full rounded-xl border border-slate-200 py-3 font-semibold text-slate-700 hover:bg-slate-50"
                @click="closeForm"
            >
                إغلاق
            </button>
            </div>
        </div>
    </Teleport>
</template>
