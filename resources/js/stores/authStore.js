import { defineStore } from 'pinia';
import { computed, ref } from 'vue';
import { axios } from '../bootstrap';

export const useAuthStore = defineStore('auth', () => {
    const user = ref(null);
    const token = ref(localStorage.getItem('auth_token'));
    const initialized = ref(false);
    const loading = ref(false);
    const error = ref(null);

    function can(permission) {
        return user.value?.permissions?.[permission] === true;
    }

    function canAny(permissions = []) {
        return permissions.some((permission) => can(permission));
    }

    const isAuthenticated = computed(() => Boolean(token.value && user.value));
    const isSuperAdmin = computed(() => user.value?.role === 'super_admin');
    const isManager = computed(() => user.value?.role === 'manager');
    const isAdmin = computed(() => can('access_admin_panel'));
    const isTeller = computed(() => user.value?.serves_queue === true);
    const canManageUsers = computed(() => can('manage_users'));
    const canControlSystem = computed(() => can('control_system'));
    const canEditTickets = computed(() => can('edit_tickets'));
    const canDeleteTickets = computed(() => can('delete_tickets'));
    const canRestoreTickets = computed(() => can('restore_tickets'));
    const canManageRoles = computed(() => can('manage_roles'));
    const homeRoute = computed(() => {
        if (can('access_admin_panel')) {
            return 'admin';
        }

        if (can('access_teller_panel')) {
            return 'teller';
        }

        return 'login';
    });
    const allowedProcessSteps = computed(() => {
        if (!isTeller.value) {
            return null;
        }

        const steps = user.value?.process_steps;

        if (!Array.isArray(steps)) {
            return null;
        }

        return steps
            .filter((step) => typeof step === 'string' || step?.enabled !== false)
            .map((step) => step.value ?? step)
            .filter((step) => typeof step === 'string');
    });

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

    async function updateProfile(payload) {
        const { data } = await axios.put('/me', payload);

        user.value = data.user;

        return data.message;
    }

    async function changePassword(payload) {
        const { data } = await axios.put('/me/password', payload);

        return data.message;
    }

    async function refreshUser() {
        if (!token.value) {
            return null;
        }

        const { data } = await axios.get('/me');
        user.value = data.user;

        return data.user;
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
        canManageUsers,
        canControlSystem,
        canEditTickets,
        canDeleteTickets,
        canRestoreTickets,
        canManageRoles,
        homeRoute,
        allowedProcessSteps,
        can,
        canAny,
        bootstrap,
        login,
        logout,
        updateProfile,
        changePassword,
        refreshUser,
    };
});
