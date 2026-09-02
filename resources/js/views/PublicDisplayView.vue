<script setup>
import { onMounted, onUnmounted, ref } from 'vue';
import { Clock, Users, Lock } from 'lucide-vue-next';
import { useQueueStore } from '../stores/queueStore';
import AppNavbar from '../components/AppNavbar.vue';

const queueStore = useQueueStore();
const clock = ref(new Date().toLocaleTimeString('ar-EG'));
const lastCalledId = ref(null);
let clockTimer = null;
let unsubscribeEcho = null;
let stopAutoRefresh = null;

function playChime() {
    try {
        const ctx = new (window.AudioContext || window.webkitAudioContext)();
        const oscillator = ctx.createOscillator();
        const gain = ctx.createGain();

        oscillator.type = 'sine';
        oscillator.frequency.setValueAtTime(880, ctx.currentTime);
        oscillator.frequency.exponentialRampToValueAtTime(440, ctx.currentTime + 0.3);
        gain.gain.setValueAtTime(0.2, ctx.currentTime);
        gain.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 0.6);

        oscillator.connect(gain);
        gain.connect(ctx.destination);
        oscillator.start();
        oscillator.stop(ctx.currentTime + 0.6);
    } catch {
        // audio not available
    }
}

function onTicketCalled(event) {
    if (event.ticket?.id && event.ticket.id !== lastCalledId.value) {
        lastCalledId.value = event.ticket.id;
        playChime();
    }
}

onMounted(() => {
    queueStore.fetchPublicStatus();

    clockTimer = setInterval(() => {
        clock.value = new Date().toLocaleTimeString('ar-EG');
    }, 1000);

    unsubscribeEcho = queueStore.subscribeEcho({
        TicketCalled: onTicketCalled,
    });

    stopAutoRefresh = queueStore.startAutoRefresh(() => queueStore.fetchPublicStatus({ silent: true }), 4000);
});

onUnmounted(() => {
    if (clockTimer) {
        clearInterval(clockTimer);
    }
    unsubscribeEcho?.();
    stopAutoRefresh?.();
});
</script>

<template>
    <div class="min-h-screen bg-slate-950 text-white">
        <AppNavbar
            title="شاشة عرض الطابور"
            subtitle="متابعة مباشرة للتذاكر"
            variant="display"
            max-width="full"
            :show-nav="false"
        >
            <div class="flex flex-wrap items-center gap-4 text-slate-300 sm:gap-6">
                <div class="flex items-center gap-2">
                    <Users class="h-5 w-5" />
                    <span>بالانتظار: {{ queueStore.stats.waiting }}</span>
                </div>
                <div class="flex items-center gap-2 font-mono text-xl sm:text-2xl">
                    <Clock class="h-5 w-5" />
                    <span>{{ clock }}</span>
                </div>
            </div>
        </AppNavbar>

        <div
            v-if="!queueStore.isSystemOpen"
            class="border-b border-red-800 bg-red-900/50 px-8 py-4 text-center text-red-200"
        >
            <Lock class="mx-auto mb-2 h-6 w-6" />
            {{ queueStore.system.closed_message || 'النظام مغلق حالياً' }}
        </div>
        <div
            v-else-if="!queueStore.isDayOpen"
            class="border-b border-amber-800 bg-amber-900/40 px-8 py-4 text-center text-amber-100"
        >
            <Lock class="mx-auto mb-2 h-6 w-6" />
            {{ queueStore.system.day_ended_message || 'انتهى استقبال الطلبات اليوم' }}
        </div>

        <div class="grid min-h-[calc(100vh-6rem)] grid-cols-1 gap-6 p-6 lg:grid-cols-3">
            <section class="lg:col-span-2">
                <h2 class="mb-4 text-xl font-semibold text-slate-300">يتم الخدمة الآن</h2>

                <div v-if="queueStore.serving.length === 0" class="flex h-80 items-center justify-center rounded-3xl border border-dashed border-slate-700 text-slate-500">
                    لا يوجد عملاء قيد الخدمة حالياً
                </div>

                <div v-else class="grid gap-4 sm:grid-cols-2">
                    <article
                        v-for="ticket in queueStore.serving"
                        :key="ticket.id"
                        class="pulse-badge rounded-3xl border border-blue-500/40 bg-gradient-to-br from-blue-600/30 to-indigo-900/40 p-8"
                    >
                        <p class="text-sm text-blue-200">{{ ticket.teller_name || ticket.counter_name || 'الشباك' }}</p>
                        <p v-if="ticket.teller_name && ticket.counter_name && ticket.teller_name !== ticket.counter_name" class="text-xs text-blue-300">
                            {{ ticket.counter_name }}
                        </p>
                        <p class="my-3 text-7xl font-black text-white">{{ ticket.ticket_number }}</p>
                        <p class="text-2xl font-semibold text-blue-100">{{ ticket.masked_name }}</p>
                    </article>
                </div>
            </section>

            <aside class="rounded-3xl border border-slate-800 bg-slate-900/60 p-6">
                <h2 class="mb-4 text-xl font-semibold text-slate-300">التالي في الانتظار</h2>

                <ul class="space-y-3">
                    <li
                        v-for="ticket in queueStore.waiting"
                        :key="ticket.id"
                        class="flex items-center justify-between rounded-2xl bg-slate-800/80 px-4 py-3"
                    >
                        <span class="text-2xl font-bold text-amber-400">{{ ticket.ticket_number }}</span>
                        <span class="text-slate-300">{{ ticket.masked_name }}</span>
                    </li>
                    <li v-if="queueStore.waiting.length === 0" class="py-8 text-center text-slate-500">
                        لا توجد تذاكر في الانتظار
                    </li>
                </ul>
            </aside>
        </div>
    </div>
</template>
