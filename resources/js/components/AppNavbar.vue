<script setup>
import { computed, ref, watch } from 'vue';
import { RouterLink, useRoute, useRouter } from 'vue-router';
import {
    ClipboardList,
    LayoutDashboard,
    LogOut,
    Menu,
    Monitor,
    Search,
    Ticket,
    UserRound,
    X,
} from 'lucide-vue-next';
import { useAuthStore } from '../stores/authStore';
import ChangePasswordButton from './ChangePasswordButton.vue';

const props = defineProps({
    title: {
        type: String,
        required: true,
    },
    subtitle: {
        type: String,
        default: '',
    },
    variant: {
        type: String,
        default: 'app',
    },
    maxWidth: {
        type: String,
        default: '7xl',
    },
    showNav: {
        type: Boolean,
        default: true,
    },
});

const authStore = useAuthStore();
const route = useRoute();
const router = useRouter();
const menuOpen = ref(false);

watch(() => route.fullPath, () => {
    menuOpen.value = false;
});

const isDisplay = computed(() => props.variant === 'display');

const shellClass = computed(() => {
    if (isDisplay.value) {
        return 'border-b border-slate-200 bg-white/95 text-slate-900 backdrop-blur-md';
    }

    return 'sticky top-0 z-40 border-b border-slate-200 bg-white/90 backdrop-blur-md';
});

const innerClass = computed(() => {
    const widths = {
        '3xl': 'max-w-3xl',
        '5xl': 'max-w-5xl',
        '7xl': 'max-w-7xl',
        full: 'max-w-none',
    };

    return [
        'mx-auto flex flex-wrap items-center justify-between gap-3 px-4 py-3 sm:px-6',
        isDisplay.value ? 'sm:py-5 sm:px-8' : '',
        widths[props.maxWidth] ?? 'max-w-7xl',
    ].join(' ');
});

const logoClass = computed(() => (
    isDisplay.value
        ? 'h-14 w-auto object-contain sm:h-16'
        : 'h-11 w-auto object-contain sm:h-12'
));

const navItems = computed(() => {
    if (!props.showNav || isDisplay.value) {
        return [];
    }

    if (authStore.isAuthenticated) {
        const items = [
            { to: '/teller', label: 'لوحة الموظف', icon: UserRound, match: 'teller' },
        ];

        if (authStore.isAdmin) {
            items.push(
                { to: '/admin', label: 'لوحة الإدارة', icon: LayoutDashboard, match: 'admin' },
                { to: '/admin/registrations', label: 'السجل والأرشيف', icon: ClipboardList, match: 'admin-registrations' },
            );
        }

        items.push({
            to: '/display',
            label: 'شاشة العرض',
            icon: Monitor,
            match: 'display',
            newTab: true,
        });

        return items;
    }

    const items = [
        { to: '/', label: 'الرئيسية', icon: Ticket, match: 'student-choice' },
        { to: '/track', label: 'متابعة التذكرة', icon: Search, match: 'track' },
    ];

    if (route.name !== 'login') {
        items.push({ to: '/login', label: 'دخول الموظفين', icon: UserRound, match: 'login' });
    }

    return items;
});

const hasMobileMenu = computed(() => {
    if (isDisplay.value) {
        return false;
    }

    return navItems.value.length > 0 || authStore.isAuthenticated;
});

function isActive(item) {
    if (item.match === 'student-choice') {
        return route.name === 'student-choice';
    }

    return route.name === item.match;
}

function linkClass(item) {
    const active = isActive(item);

    if (isDisplay.value) {
        return active
            ? 'bg-indigo-600 text-white'
            : 'border border-slate-200 text-slate-700 hover:bg-slate-50';
    }

    return active
        ? 'bg-indigo-600 text-white shadow-sm'
        : 'border border-slate-200 text-slate-700 hover:bg-slate-50';
}

async function logout() {
    const scanRedirect = route.name === 'ticket-scan' ? route.fullPath : null;

    await authStore.logout();

    if (scanRedirect) {
        await router.push({ name: 'login', query: { redirect: scanRedirect } });
        return;
    }

    await router.push('/login');
}
</script>

<template>
    <header :class="shellClass">
        <div :class="innerClass">
            <div class="flex min-w-0 items-center gap-3">
                <img
                    :src="'/logo.webp'"
                    alt="جامعة برج العرب التكنولوجية"
                    width="120"
                    height="48"
                    decoding="async"
                    fetchpriority="high"
                    :class="logoClass"
                />
                <div class="min-w-0">
                    <h1 class="truncate text-lg font-bold text-slate-900 sm:text-2xl">
                        {{ title }}
                    </h1>
                    <p
                        v-if="subtitle"
                        class="truncate text-xs text-slate-500 sm:text-sm"
                    >
                        {{ subtitle }}
                    </p>
                </div>
            </div>

            <div class="flex shrink-0 items-center gap-2">
                <slot />

                <nav v-if="navItems.length" class="hidden items-center gap-2 lg:flex" aria-label="التنقل الرئيسي">
                    <RouterLink
                        v-for="item in navItems"
                        :key="item.to"
                        :to="item.to"
                        :target="item.newTab ? '_blank' : undefined"
                        :rel="item.newTab ? 'noopener noreferrer' : undefined"
                        class="flex items-center gap-2 rounded-xl px-3 py-2 text-sm font-semibold"
                        :class="linkClass(item)"
                    >
                        <component :is="item.icon" class="h-4 w-4" />
                        {{ item.label }}
                    </RouterLink>
                </nav>

                <div v-if="authStore.isAuthenticated && !isDisplay" class="hidden items-center gap-2 lg:flex">
                    <ChangePasswordButton />
                    <button
                        type="button"
                        class="flex items-center gap-2 rounded-xl border border-slate-200 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                        @click="logout"
                    >
                        <LogOut class="h-4 w-4" />
                        خروج
                    </button>
                </div>

                <button
                    v-if="hasMobileMenu"
                    type="button"
                    class="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-slate-200 text-slate-700 lg:hidden"
                    :aria-expanded="menuOpen"
                    aria-label="فتح القائمة"
                    @click="menuOpen = !menuOpen"
                >
                    <X v-if="menuOpen" class="h-5 w-5" />
                    <Menu v-else class="h-5 w-5" />
                </button>
            </div>
        </div>

        <div
            v-if="menuOpen && hasMobileMenu"
            class="border-t border-slate-200 bg-white lg:hidden"
        >
            <nav class="mx-auto flex max-w-7xl flex-col gap-2 px-4 py-3 sm:px-6" aria-label="التنقل للجوال">
                <RouterLink
                    v-for="item in navItems"
                    :key="`mobile-${item.to}`"
                    :to="item.to"
                    :target="item.newTab ? '_blank' : undefined"
                    :rel="item.newTab ? 'noopener noreferrer' : undefined"
                    class="flex items-center gap-2 rounded-xl px-4 py-3 text-sm font-semibold"
                    :class="linkClass(item)"
                >
                    <component :is="item.icon" class="h-4 w-4" />
                    {{ item.label }}
                </RouterLink>

                <template v-if="authStore.isAuthenticated">
                    <ChangePasswordButton block />
                    <button
                        type="button"
                        class="flex items-center justify-center gap-2 rounded-xl border border-slate-200 px-4 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                        @click="logout"
                    >
                        <LogOut class="h-4 w-4" />
                        خروج
                    </button>
                </template>
            </nav>
        </div>
    </header>
</template>
