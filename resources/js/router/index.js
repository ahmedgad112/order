import { createRouter, createWebHistory } from 'vue-router';
import { useAuthStore } from '../stores/authStore';

const routes = [
    {
        path: '/',
        name: 'kiosk',
        component: () => import('../views/PublicKioskView.vue'),
        meta: { title: 'إصدار تذكرة' },
    },
    {
        path: '/display',
        name: 'display',
        component: () => import('../views/PublicDisplayView.vue'),
        meta: { title: 'شاشة العرض' },
    },
    {
        path: '/track',
        name: 'track',
        component: () => import('../views/UserTrackView.vue'),
        meta: { title: 'متابعة التذكرة' },
    },
    {
        path: '/t/:token',
        name: 'ticket-scan',
        component: () => import('../views/TicketScanView.vue'),
        meta: { title: 'بيانات التذكرة' },
    },
    {
        path: '/login',
        name: 'login',
        component: () => import('../views/LoginView.vue'),
        meta: { title: 'تسجيل الدخول', guest: true },
    },
    {
        path: '/teller',
        name: 'teller',
        component: () => import('../views/TellerDashboardView.vue'),
        meta: { title: 'لوحة الموظف', requiresAuth: true, roles: ['teller', 'manager', 'super_admin'] },
    },
    {
        path: '/admin',
        name: 'admin',
        component: () => import('../views/AdminDashboardView.vue'),
        meta: { title: 'لوحة الإدارة', requiresAuth: true, roles: ['manager', 'super_admin'] },
    },
    {
        path: '/admin/registrations',
        name: 'admin-registrations',
        component: () => import('../views/AdminRegistrationsView.vue'),
        meta: { title: 'سجل التسجيلات', requiresAuth: true, roles: ['manager', 'super_admin'] },
    },
];

const router = createRouter({
    history: createWebHistory(),
    routes,
});

router.beforeEach(async (to) => {
    const authStore = useAuthStore();

    if (!authStore.initialized) {
        await authStore.bootstrap();
    }

    document.title = to.meta.title
        ? `${to.meta.title} | نظام إدارة الأدوار`
        : 'نظام إدارة الأدوار';

    if (to.meta.requiresAuth && !authStore.isAuthenticated) {
        return { name: 'login', query: { redirect: to.fullPath } };
    }

    if (to.meta.guest && authStore.isAuthenticated) {
        return { name: authStore.homeRoute };
    }

    if (to.meta.roles && authStore.user) {
        const allowed = to.meta.roles.includes(authStore.user.role);
        if (!allowed) {
            return { name: authStore.homeRoute };
        }
    }

    return true;
});

export default router;
