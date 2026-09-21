<script setup>
import { computed, onMounted, onUnmounted, ref, useSlots, watch } from 'vue';
import { RouterLink, useRoute, useRouter } from 'vue-router';
import {
    ClipboardList,
    KeyRound,
    LayoutDashboard,
    LogOut,
    Menu,
    Mic,
    Monitor,
    Search,
    Ticket,
    UserRound,
    Users,
    X,
} from 'lucide-vue-next';
import { useAuthStore } from '../stores/authStore';
import AccountSettingsButton from './AccountSettingsButton.vue';

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
    pageClass: {
        type: String,
        default: 'bg-slate-100',
    },
});

const authStore = useAuthStore();
const route = useRoute();
const router = useRouter();
const slots = useSlots();
const menuOpen = ref(false);

watch(() => route.fullPath, () => {
    menuOpen.value = false;
});

watch(menuOpen, (open) => {
    document.body.style.overflow = open ? 'hidden' : '';
});

function closeMenu() {
    menuOpen.value = false;
}

function onKeydown(event) {
    if (event.key === 'Escape') {
        closeMenu();
    }
}

onMounted(() => {
    window.addEventListener('keydown', onKeydown);
});

onUnmounted(() => {
    window.removeEventListener('keydown', onKeydown);
    document.body.style.overflow = '';
});

const isDisplay = computed(() => props.variant === 'display');
const hasActions = computed(() => Boolean(slots.actions));

const displayInnerClass = computed(() => {
    const widths = {
        '3xl': 'max-w-3xl',
        '5xl': 'max-w-5xl',
        '7xl': 'max-w-7xl',
        full: 'max-w-none',
    };

    return [
        'mx-auto flex items-center justify-between gap-3 px-4 py-3 sm:px-6 sm:py-5',
        widths[props.maxWidth] ?? 'max-w-7xl',
    ].join(' ');
});

const navItems = computed(() => {
    if (!props.showNav || isDisplay.value) {
        return [];
    }

    if (authStore.isAuthenticated) {
        const items = [];

        if (authStore.can('access_teller_panel')) {
            items.push({ to: '/teller', label: 'لوحة الموظف', icon: UserRound, match: 'teller' });
        }

        if (authStore.can('access_admin_panel')) {
            items.push(
                { to: '/admin', label: 'لوحة الإدارة', icon: LayoutDashboard, match: 'admin' },
            );
        }

        if (authStore.canManageUsers) {
            items.push({ to: '/admin/users', label: 'المستخدمون', icon: Users, match: 'admin-users' });
        }

        if (authStore.canManageRoles) {
            items.push({
                to: '/admin/permissions',
                label: 'الأدوار',
                icon: KeyRound,
                match: 'admin-permissions',
            });
        }

        if (authStore.can('access_registrations')) {
            items.push({
                to: '/admin/registrations',
                label: 'السجل والأرشيف',
                icon: ClipboardList,
                match: 'admin-registrations',
            });
        }

        if (authStore.can('access_mic')) {
            items.push({ to: '/mic', label: 'الميكروفون', icon: Mic, match: 'mic' });
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

const hasSidebar = computed(() => !isDisplay.value);

const userRoleLabel = computed(() => authStore.user?.role_label ?? '');

function isActive(item) {
    if (item.match === 'student-choice') {
        return route.name === 'student-choice';
    }

    return route.name === item.match;
}

function navLinkClass(item) {
    return isActive(item)
        ? 'bg-indigo-600 text-white shadow-sm'
        : 'text-slate-700 hover:bg-slate-100';
}

async function logout() {
    const scanRedirect = route.name === 'ticket-scan' ? route.fullPath : null;

    await authStore.logout();
    closeMenu();

    if (scanRedirect) {
        await router.push({ name: 'login', query: { redirect: scanRedirect } });
        return;
    }

    await router.push('/login');
}
</script>

<template>
    <header
        v-if="isDisplay"
        class="border-b border-slate-200 bg-white/95 text-slate-900 backdrop-blur-md"
    >
        <div :class="displayInnerClass">
            <div class="flex min-w-0 items-center gap-3">
                <img
                    :src="'/logo.webp'"
                    alt="جامعة برج العرب التكنولوجية"
                    width="120"
                    height="48"
                    decoding="async"
                    fetchpriority="high"
                    class="h-14 w-auto object-contain sm:h-16"
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

            <div class="flex min-w-0 shrink-0 items-center gap-2">
                <slot />
            </div>
        </div>
    </header>

    <div
        v-else
        class="flex min-h-dvh flex-col"
        :class="pageClass"
    >
        <header class="sticky top-0 z-40 border-b border-slate-200 bg-white/95 backdrop-blur-md lg:hidden">
            <div class="flex items-center gap-2 px-3 py-2.5 ps-[max(0.75rem,env(safe-area-inset-right))] pe-[max(0.75rem,env(safe-area-inset-left))]">
                <button
                    v-if="hasSidebar"
                    type="button"
                    class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border border-slate-200 text-slate-700"
                    :aria-expanded="menuOpen"
                    aria-controls="app-sidebar"
                    :aria-label="menuOpen ? 'إغلاق القائمة' : 'فتح القائمة'"
                    @click="menuOpen = !menuOpen"
                >
                    <X v-if="menuOpen" class="h-5 w-5" />
                    <Menu v-else class="h-5 w-5" />
                </button>
                <img
                    :src="'/logo.webp'"
                    alt="جامعة برج العرب التكنولوجية"
                    width="96"
                    height="40"
                    decoding="async"
                    fetchpriority="high"
                    class="h-8 w-auto shrink-0 object-contain sm:h-9"
                />
                <div class="min-w-0 flex-1">
                    <h1 class="truncate text-sm font-bold leading-tight text-slate-900 sm:text-base">
                        {{ title }}
                    </h1>
                    <p
                        v-if="subtitle"
                        class="truncate text-[11px] leading-tight text-slate-500 sm:text-xs"
                    >
                        {{ subtitle }}
                    </p>
                </div>
                <div
                    v-if="hasActions"
                    class="max-w-[40%] shrink-0 sm:max-w-none"
                >
                    <slot name="actions" />
                </div>
            </div>
        </header>

        <div
            v-if="menuOpen"
            class="fixed inset-0 z-[60] bg-slate-900/40 lg:hidden"
            @click="closeMenu"
        />

        <aside
            id="app-sidebar"
            class="fixed inset-y-0 start-0 z-[70] flex w-[min(18rem,88vw)] flex-col border-e border-slate-200 bg-white shadow-xl transition-transform duration-200 lg:w-72 lg:translate-x-0 lg:shadow-none"
            :class="menuOpen ? 'translate-x-0' : 'pointer-events-none translate-x-full lg:pointer-events-auto lg:translate-x-0'"
        >
            <div class="flex items-start gap-3 border-b border-slate-100 px-4 py-4 pt-[max(1rem,env(safe-area-inset-top))]">
                <img
                    :src="'/logo.webp'"
                    alt="جامعة برج العرب التكنولوجية"
                    width="120"
                    height="48"
                    decoding="async"
                    class="hidden h-11 w-auto shrink-0 object-contain lg:block"
                />
                <div class="min-w-0 flex-1">
                    <p class="truncate text-base font-bold text-slate-900">
                        {{ title }}
                    </p>
                    <p
                        v-if="subtitle"
                        class="mt-0.5 line-clamp-2 text-xs leading-5 text-slate-500"
                    >
                        {{ subtitle }}
                    </p>
                </div>
                <button
                    type="button"
                    class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-xl border border-slate-200 text-slate-600 lg:hidden"
                    aria-label="إغلاق القائمة"
                    @click="closeMenu"
                >
                    <X class="h-4 w-4" />
                </button>
            </div>

            <nav
                class="flex flex-1 flex-col gap-1 overflow-y-auto px-3 py-4"
                aria-label="التنقل الرئيسي"
            >
                <RouterLink
                    v-for="item in navItems"
                    :key="item.to"
                    :to="item.to"
                    :target="item.newTab ? '_blank' : undefined"
                    :rel="item.newTab ? 'noopener noreferrer' : undefined"
                    class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-semibold"
                    :class="navLinkClass(item)"
                >
                    <component :is="item.icon" class="h-4 w-4 shrink-0" />
                    <span class="min-w-0 truncate">{{ item.label }}</span>
                </RouterLink>

                <div
                    v-if="hasActions"
                    class="mt-3 hidden lg:block"
                >
                    <slot name="actions" />
                </div>
            </nav>

            <div
                v-if="authStore.isAuthenticated"
                class="mt-auto space-y-2 border-t border-slate-100 px-3 py-4 pb-[max(1rem,env(safe-area-inset-bottom))]"
            >
                <div
                    v-if="authStore.user?.name"
                    class="px-1 pb-1"
                >
                    <p class="truncate text-sm font-bold text-slate-900">
                        {{ authStore.user.name }}
                    </p>
                    <p
                        v-if="userRoleLabel"
                        class="truncate text-xs text-slate-500"
                    >
                        {{ userRoleLabel }}
                    </p>
                </div>
                <AccountSettingsButton block />
                <button
                    type="button"
                    class="flex w-full items-center justify-center gap-2 rounded-xl border border-slate-200 px-3 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                    @click="logout"
                >
                    <LogOut class="h-4 w-4" />
                    خروج
                </button>
            </div>
        </aside>

        <div class="flex min-h-0 min-w-0 flex-1 flex-col overflow-x-clip pb-[env(safe-area-inset-bottom)] lg:ps-72">
            <slot />
        </div>
    </div>
</template>
