import { createRouter, createWebHistory } from 'vue-router';
import { useAuthStore } from '../stores/authStore';

const routes = [
    {
        path: '/',
        name: 'student-choice',
        component: () => import('../views/StudentChoiceView.vue'),
        meta: { title: 'نظام إدارة الأدوار' },
    },
    {
        path: '/new-student',
        redirect: { name: 'student-choice' },
    },
    {
        path: '/current-student',
        redirect: { name: 'student-choice' },
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
        meta: {
            title: 'بيانات التذكرة',
            requiresAuth: true,
            permissions: ['access_teller_panel'],
        },
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
        meta: { title: 'لوحة الموظف', requiresAuth: true, permissions: ['access_teller_panel'] },
    },
    {
        path: '/admin',
        name: 'admin',
        component: () => import('../views/AdminDashboardView.vue'),
        meta: { title: 'لوحة الإدارة', requiresAuth: true, permissions: ['access_admin_panel'] },
    },
    {
        path: '/admin/registrations',
        name: 'admin-registrations',
        component: () => import('../views/AdminRegistrationsView.vue'),
        meta: { title: 'السجل والأرشيف', requiresAuth: true, permissions: ['access_registrations'] },
    },
    {
        path: '/admin/users',
        name: 'admin-users',
        component: () => import('../views/AdminUsersView.vue'),
        meta: { title: 'إدارة المستخدمين', requiresAuth: true, permissions: ['manage_users'] },
    },
    {
        path: '/admin/permissions',
        name: 'admin-permissions',
        component: () => import('../views/AdminPermissionsView.vue'),
        meta: { title: 'الأدوار والصلاحيات', requiresAuth: true, permissions: ['manage_roles'] },
    },
    {
        path: '/mic',
        name: 'mic',
        component: () => import('../views/MicView.vue'),
        meta: { title: 'الميكروفون', requiresAuth: true, permissions: ['access_mic'] },
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

    if (to.meta.permissions?.length && authStore.user) {
        const allowed = authStore.canAny(to.meta.permissions);
        if (!allowed) {
            return { name: authStore.homeRoute === to.name ? 'login' : authStore.homeRoute };
        }
    }

    return true;
});

const prefetchByRoute = {
    'student-choice': ['track', 'login'],
    track: ['student-choice'],
    login: ['teller', 'admin'],
    teller: ['admin', 'admin-registrations'],
    admin: ['teller', 'admin-registrations', 'admin-users', 'admin-permissions'],
    'admin-registrations': ['admin', 'teller', 'admin-users'],
    'admin-users': ['admin', 'admin-registrations', 'admin-permissions'],
    'admin-permissions': ['admin', 'admin-users'],
};

router.afterEach((to) => {
    const names = prefetchByRoute[to.name] ?? [];

    names.forEach((name) => {
        const matched = router.resolve({ name }).matched.at(-1)?.components?.default;

        if (typeof matched === 'function') {
            matched();
        }
    });
});

export default router;
