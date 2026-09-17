<script setup>
import { computed, onMounted } from 'vue';
import { useAuthStore } from '../stores/authStore';
import { useQueueStore } from '../stores/queueStore';
import AdminUserManagement from '../components/AdminUserManagement.vue';
import AppNavbar from '../components/AppNavbar.vue';

const authStore = useAuthStore();
const queueStore = useQueueStore();

const navbarSubtitle = computed(() => (
    authStore.isSuperAdmin
        ? 'إنشاء وتعديل حسابات السوبر أدمن والمديرين والموظفين'
        : 'إنشاء وتعديل حسابات الموظفين'
));

onMounted(() => {
    queueStore.fetchUsers().catch(() => {});
});
</script>

<template>
    <div class="min-h-screen bg-slate-100">
        <AppNavbar title="إدارة المستخدمين" :subtitle="navbarSubtitle" />

        <main class="mx-auto max-w-7xl space-y-6 px-4 py-6 sm:px-6">
            <AdminUserManagement />
        </main>
    </div>
</template>
