import { defineStore } from 'pinia';
import { computed, ref } from 'vue';
import { axios } from '../bootstrap';

export const useAuthStore = defineStore('auth', () => {
    const user = ref(null);
    const token = ref(localStorage.getItem('auth_token'));
    const initialized = ref(false);
    const loading = ref(false);
    const error = ref(null);

    const isAuthenticated = computed(() => Boolean(token.value && user.value));
    const isSuperAdmin = computed(() => user.value?.role === 'super_admin');
    const isManager = computed(() => user.value?.role === 'manager');
    const isAdmin = computed(() => isSuperAdmin.value || isManager.value);
    const isTeller = computed(() => user.value?.role === 'teller');
    const homeRoute = computed(() => (isAdmin.value ? 'admin' : 'teller'));

    async function bootstrap() {
        if (!token.value) {
            initialized.value = true;
            return;
        }

        axios.defaults.headers.common.Authorization = `Bearer ${token.value}`;

        try {
            const { data } = await axios.get('/me');
            user.value = data.user;
        } catch {
            await logout();
        } finally {
            initialized.value = true;
        }
    }

    async function login(credentials) {
        loading.value = true;
        error.value = null;

        try {
            const { data } = await axios.post('/login', credentials);
            token.value = data.token;
            user.value = data.user;
            localStorage.setItem('auth_token', data.token);
            axios.defaults.headers.common.Authorization = `Bearer ${data.token}`;
            return data.user;
        } catch (err) {
            error.value = err.response?.data?.message
                ?? err.response?.data?.errors?.email?.[0]
                ?? 'فشل تسجيل الدخول.';
            throw err;
        } finally {
            loading.value = false;
        }
    }

    async function logout() {
        try {
            if (token.value) {
                await axios.post('/logout');
            }
        } catch {
            // ignore
        } finally {
            token.value = null;
            user.value = null;
            localStorage.removeItem('auth_token');
            delete axios.defaults.headers.common.Authorization;
        }
    }

    async function changePassword(payload) {
        const { data } = await axios.put('/me/password', payload);

        return data.message;
    }

    return {
        user,
        token,
        initialized,
        loading,
        error,
        isAuthenticated,
        isSuperAdmin,
        isManager,
        isAdmin,
        isTeller,
        homeRoute,
        bootstrap,
        login,
        logout,
        changePassword,
    };
});
