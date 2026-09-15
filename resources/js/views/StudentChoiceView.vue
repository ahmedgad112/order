<script setup>
import { onMounted, onUnmounted } from 'vue';
import { RouterLink } from 'vue-router';
import { Lock, Search, UserRound } from 'lucide-vue-next';
import { useQueueStore } from '../stores/queueStore';
import AppNavbar from '../components/AppNavbar.vue';

const queueStore = useQueueStore();

let stopAutoRefresh = null;
let unsubscribeEcho = null;

onMounted(() => {
    queueStore.fetchPublicStatus();
    unsubscribeEcho = queueStore.subscribeEcho();
    stopAutoRefresh = queueStore.startAutoRefresh(() => queueStore.fetchPublicStatus({ silent: true }), 5000);
});

onUnmounted(() => {
    unsubscribeEcho?.();
    stopAutoRefresh?.();
});
</script>

<template>
    <div class="min-h-screen bg-gradient-to-br from-blue-50 via-white to-indigo-50">
        <AppNavbar title="نظام إدارة الأدوار" subtitle="التسجيل عند الموظف" max-width="5xl" />

        <main class="mx-auto max-w-3xl px-6 py-10">
            <div
                v-if="!queueStore.isSystemOpen"
                class="mb-6 rounded-3xl border border-red-200 bg-red-50 p-6 text-center"
            >
                <Lock class="mx-auto mb-3 h-10 w-10 text-red-500" />
                <h2 class="text-xl font-bold text-red-800">النظام مغلق حالياً</h2>
                <p class="mt-2 text-red-700">{{ queueStore.system.closed_message || 'لا يمكن إصدار أدوار جديدة في الوقت الحالي.' }}</p>
            </div>
            <div
                v-else-if="!queueStore.isAcceptingTickets"
                class="mb-6 rounded-3xl border border-amber-200 bg-amber-50 p-6 text-center"
            >
                <Lock class="mx-auto mb-3 h-10 w-10 text-amber-500" />
                <h2 class="text-xl font-bold text-amber-800">انتهى استقبال الطلبات اليوم</h2>
                <p class="mt-2 text-amber-700">{{ queueStore.system.day_ended_message || 'لا يمكن تسجيل ناس جديدة الآن. يمكن متابعة الطلبات الحالية.' }}</p>
            </div>

            <div class="rounded-3xl border border-slate-200 bg-white p-8 text-center shadow-xl">
                <h2 class="text-3xl font-bold text-slate-800">التسجيل عند الموظف</h2>
                <p class="mt-3 text-slate-500">
                    إصدار الدور يتم من الموظف. توجه إلى شباك التسجيل لإدخال اسم الطالب ورقم الطلب ونوعه.
                </p>
                <RouterLink
                    to="/login"
                    class="mt-6 inline-flex items-center justify-center gap-2 rounded-2xl bg-indigo-600 px-5 py-3 text-base font-bold text-white hover:bg-indigo-700"
                >
                    <UserRound class="h-5 w-5" />
                    دخول الموظفين
                </RouterLink>
            </div>

            <div class="mt-6 rounded-3xl border border-slate-200 bg-white p-5 shadow-xl">
                <p class="mb-4 text-center text-sm text-slate-500">اعرف رقمك وحالة تذكرتك</p>
                <RouterLink
                    to="/track"
                    class="flex w-full items-center justify-center gap-2 rounded-2xl bg-blue-600 px-5 py-3 text-base font-bold text-white transition hover:bg-blue-700"
                >
                    <Search class="h-5 w-5" />
                    متابعة دورك
                </RouterLink>
            </div>
        </main>
    </div>
</template>
