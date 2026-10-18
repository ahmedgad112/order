<script setup>
import { onMounted, onUnmounted } from 'vue';
import { RouterLink } from 'vue-router';
import { GraduationCap, Lock, Search, UserRound } from 'lucide-vue-next';
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
    <AppNavbar
        title="نظام إدارة الأدوار"
        subtitle="التسجيل عند الموظف"
        page-class="bg-gradient-to-br from-blue-50 via-white to-indigo-50"
    >
        <main class="mx-auto w-full max-w-3xl px-3 py-5 sm:px-6 sm:py-10">
            <div
                v-if="!queueStore.isSystemOpen"
                class="mb-5 rounded-3xl border border-red-200 bg-red-50 p-5 text-center sm:mb-6 sm:p-6"
            >
                <Lock class="mx-auto mb-3 h-10 w-10 text-red-500" />
                <h2 class="text-lg font-bold text-red-800 sm:text-xl">النظام مغلق حالياً</h2>
                <p class="mt-2 text-sm text-red-700 sm:text-base">{{ queueStore.system.closed_message || 'لا يمكن إصدار أدوار جديدة في الوقت الحالي.' }}</p>
            </div>
            <div
                v-else-if="!queueStore.isAcceptingTickets"
                class="mb-5 rounded-3xl border border-amber-200 bg-amber-50 p-5 text-center sm:mb-6 sm:p-6"
            >
                <Lock class="mx-auto mb-3 h-10 w-10 text-amber-500" />
                <h2 class="text-lg font-bold text-amber-800 sm:text-xl">انتهى استقبال الطلبات اليوم</h2>
                <p class="mt-2 text-sm text-amber-700 sm:text-base">{{ queueStore.system.day_ended_message || 'لا يمكن تسجيل ناس جديدة الآن. يمكن متابعة الطلبات الحالية.' }}</p>
            </div>

            <div class="rounded-3xl border border-slate-200 bg-white p-5 text-center shadow-xl sm:p-8">
                <h2 class="text-2xl font-bold text-slate-800 sm:text-3xl">تسجيل بياناتك</h2>
                <p class="mt-3 text-sm text-slate-500 sm:text-base">
                    اكتب اسمك ورقمك القومي والأكاديمي وقسمك وسكشنك، وبعد رقم المجموعة اختار A أو B. هتاخد دور، وموظف الشباك يقدر يعلّمه تم.
                </p>
                <div class="mt-6 flex flex-col gap-3 sm:flex-row sm:justify-center">
                    <RouterLink
                        to="/student-register"
                        class="inline-flex w-full items-center justify-center gap-2 rounded-2xl bg-emerald-600 px-5 py-3 text-base font-bold text-white hover:bg-emerald-700 sm:w-auto"
                    >
                        <GraduationCap class="h-5 w-5" />
                        سجّل بياناتك
                    </RouterLink>
                    <RouterLink
                        to="/student-register#lookup"
                        class="inline-flex w-full items-center justify-center gap-2 rounded-2xl border border-indigo-200 bg-indigo-50 px-5 py-3 text-base font-bold text-indigo-800 hover:bg-indigo-100 sm:w-auto"
                    >
                        <Search class="h-5 w-5" />
                        ابحث عن دورك
                    </RouterLink>
                </div>
            </div>

            <div class="mt-5 rounded-3xl border border-slate-200 bg-white p-5 text-center shadow-xl sm:mt-6 sm:p-8">
                <h2 class="text-2xl font-bold text-slate-800 sm:text-3xl">التسجيل عند الموظف</h2>
                <p class="mt-3 text-sm text-slate-500 sm:text-base">
                    إصدار الدور يتم من الموظف. توجه إلى شباك التسجيل لإدخال اسم الطالب ورقم الطلب ونوعه.
                </p>
                <RouterLink
                    to="/login"
                    class="mt-6 inline-flex w-full items-center justify-center gap-2 rounded-2xl bg-indigo-600 px-5 py-3 text-base font-bold text-white hover:bg-indigo-700 sm:w-auto"
                >
                    <UserRound class="h-5 w-5" />
                    دخول الموظفين
                </RouterLink>
            </div>

            <div class="mt-5 rounded-3xl border border-slate-200 bg-white p-5 shadow-xl sm:mt-6">
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
    </AppNavbar>
</template>
