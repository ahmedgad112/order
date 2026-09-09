<script setup>
import { RouterLink } from 'vue-router';
import { GraduationCap, UserPlus } from 'lucide-vue-next';
import AppNavbar from '../components/AppNavbar.vue';
</script>

<template>
    <div class="min-h-screen bg-gradient-to-br from-blue-50 via-white to-indigo-50">
        <AppNavbar title="نظام إدارة الأدوار" subtitle="اختر نوع الطالب" max-width="5xl" />

        <main class="mx-auto max-w-3xl px-6 py-10">
            <div class="rounded-3xl border border-slate-200 bg-white p-8 shadow-xl">
                <h2 class="mb-3 text-center text-3xl font-bold text-slate-800">مرحباً بك</h2>
                <p class="mb-8 text-center text-slate-500">اختر نوع الطالب للمتابعة</p>

                <div class="grid gap-4 sm:grid-cols-2">
                    <RouterLink
                        to="/new-student"
                        class="flex flex-col items-center gap-4 rounded-3xl border border-slate-200 bg-blue-50 px-6 py-10 text-center transition hover:border-blue-400 hover:bg-blue-100 hover:shadow-lg"
                    >
                        <span class="flex h-16 w-16 items-center justify-center rounded-full bg-blue-600 text-white">
                            <UserPlus class="h-8 w-8" />
                        </span>
                        <span class="text-xl font-bold text-slate-800">طالب جديد</span>
                        <span class="text-sm text-slate-500">التقديم للالتحاق بالجامعة</span>
                    </RouterLink>

                    <RouterLink
                        to="/current-student"
                        class="flex flex-col items-center gap-4 rounded-3xl border border-slate-200 bg-indigo-50 px-6 py-10 text-center transition hover:border-indigo-400 hover:bg-indigo-100 hover:shadow-lg"
                    >
                        <span class="flex h-16 w-16 items-center justify-center rounded-full bg-indigo-600 text-white">
                            <GraduationCap class="h-8 w-8" />
                        </span>
                        <span class="text-xl font-bold text-slate-800">طالب حالي</span>
                        <span class="text-sm text-slate-500">طالب في الجامعة — فرقة ثانية</span>
                    </RouterLink>
                </div>
            </div>
        </main>
    </div>
</template>
