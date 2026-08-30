<script setup>
import { onMounted, onUnmounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import { LogIn } from 'lucide-vue-next';
import { useAuthStore } from '../stores/authStore';

const authStore = useAuthStore();
const router = useRouter();

const form = ref({
    email: '',
    password: '',
});

async function submit() {
    try {
        const user = await authStore.login(form.value);
        const redirect = router.currentRoute.value.query.redirect;
        if (redirect) {
            await router.push(String(redirect));
            return;
        }
        await router.push(user.role === 'admin' ? '/admin' : '/teller');
    } catch {
        // error shown via store
    }
}

onMounted(() => {
    document.title = 'تسجيل الدخول | نظام الطوابير';
});
</script>

<template>
    <div class="flex min-h-screen items-center justify-center bg-slate-100 px-4">
        <div class="w-full max-w-md rounded-3xl bg-white p-8 shadow-xl">
            <div class="mb-8 text-center">
                <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-2xl bg-blue-600 text-white">
                    <LogIn class="h-8 w-8" />
                </div>
                <h1 class="text-2xl font-bold text-slate-900">تسجيل الدخول</h1>
                <p class="text-sm text-slate-500">لوحة الموظفين والإدارة</p>
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
                    <input
                        v-model="form.password"
                        type="password"
                        required
                        class="w-full rounded-xl border border-slate-200 px-4 py-3 outline-none focus:border-blue-500 focus:ring-4 focus:ring-blue-100"
                    />
                </div>
                <button
                    type="submit"
                    :disabled="authStore.loading"
                    class="w-full rounded-xl bg-blue-600 py-3 font-bold text-white hover:bg-blue-700 disabled:opacity-60"
                >
                    {{ authStore.loading ? 'جاري الدخول...' : 'دخول' }}
                </button>
            </form>

            <p class="mt-6 text-center text-xs text-slate-400">
                تجريبي: teller1@queue.local / password
            </p>
        </div>
    </div>
</template>
