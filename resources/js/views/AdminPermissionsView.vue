<script setup>
import AppNavbar from '../components/AppNavbar.vue';
import AdminRolePermissions from '../components/AdminRolePermissions.vue';
</script>

<template>
    <AppNavbar
        title="صلاحيات الأدوار"
        subtitle="تحديد الأزرار الظاهرة لكل دور في النظام"
    >
        <main class="mx-auto w-full max-w-7xl space-y-6 px-3 py-4 sm:px-6 sm:py-6">
            <AdminRolePermissions />
        </main>
    </AppNavbar>
</template>
