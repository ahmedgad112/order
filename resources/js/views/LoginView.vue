<script setup>
import { computed, onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '../stores/authStore';
import AppNavbar from '../components/AppNavbar.vue';
import PasswordInput from '../components/PasswordInput.vue';

const authStore = useAuthStore();
const router = useRouter();

const form = ref({
    email: '',
    password: '',
});

const staffScanRedirect = computed(() => {
    const redirect = router.currentRoute.value.query.redirect;

    return typeof redirect === 'string' && redirect.startsWith('/t/');
});

async function submit() {
    try {
        await authStore.login(form.value);
        const redirect = router.currentRoute.value.query.redirect;
        if (redirect) {
            await router.push(String(redirect));
            return;
        }
        await router.push({ name: authStore.homeRoute });
    } catch {
        // error shown via store
    }
}

onMounted(() => {
    document.title = 'تسجيل الدخول | نظام إدارة الأدوار';
});
</script>

<template>
    <div class="min-h-screen bg-slate-100">
        <AppNavbar title="نظام إدارة الأدوار" subtitle="لوحة الموظفين والإدارة" max-width="7xl" />

        <div class="flex items-center justify-center px-4 py-12">
            <div class="w-full max-w-md rounded-3xl bg-white p-8 shadow-xl">
            <div class="mb-8 text-center">
                <img
                    :src="'/logo.webp'"
                    alt="جامعة برج العرب التكنولوجية"
                    width="160"
                    height="96"
                    decoding="async"
                    class="mx-auto mb-4 h-24 w-auto object-contain"
                />
                <h1 class="text-2xl font-bold text-slate-900">تسجيل الدخول</h1>
                <p class="text-sm text-slate-500">لوحة الموظفين والإدارة</p>
                <p
                    v-if="staffScanRedirect"
                    class="mt-3 rounded-xl bg-amber-50 px-4 py-3 text-sm font-semibold text-amber-800"
                >
                    عرض بيانات التذكرة متاح لموظفي النظام فقط. سجّل الدخول للمتابعة.
                </p>
            </div>

            <p v-if="authStore.error" class="mb-4 rounded-xl bg-red-50 px-4 py-3 text-center text-sm text-red-700">
                {{ authStore.error }}
            </p>

            <form class="space-y-5" @submit.prevent="submit">
                <div>
                    <label class="mb-2 block text-sm font-semibold text-slate-700">البريد الإلكتروني</label>
                    <input
                        v-model="form.email"
                        type="email"
                        required
                        class="w-full rounded-xl border border-slate-200 px-4 py-3 outline-none focus:border-blue-500 focus:ring-4 focus:ring-blue-100"
                        placeholder="email@example.com"
                    />
                </div>
                <div>
                    <label class="mb-2 block text-sm font-semibold text-slate-700">كلمة المرور</label>
                    <PasswordInput v-model="form.password" required autocomplete="current-password" />
                </div>
                <button
                    type="submit"
                    :disabled="authStore.loading"
                    class="w-full rounded-xl bg-blue-600 py-3 font-bold text-white hover:bg-blue-700 disabled:opacity-60"
                >
                    {{ authStore.loading ? 'جاري الدخول...' : 'دخول' }}
                </button>
            </form>
            </div>
        </div>
    </div>
</template>
