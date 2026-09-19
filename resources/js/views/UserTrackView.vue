<script setup>
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import {
    BellRing,
    CheckCircle2,
    Clock,
    Hash,
    FileText,
    RefreshCw,
    Search,
    Ticket,
    XCircle,
} from 'lucide-vue-next';
import { useQueueStore } from '../stores/queueStore';
import AppNavbar from '../components/AppNavbar.vue';

const queueStore = useQueueStore();

const searchType = ref('national_id');
const searchValue = ref('');
const trackedTicket = ref(null);
const fieldError = ref('');
const isRefreshing = ref(false);

function restrictDigitInput(event, maxLength) {
    const digits = event.target.value.replace(/\D/g, '').slice(0, maxLength);
    event.target.value = digits;

    return digits;
}

function onNationalIdInput(event) {
    searchType.value = 'national_id';
    searchValue.value = restrictDigitInput(event, 14);
}

function onSeatNumberInput(event) {
    searchType.value = 'seat_number';
    searchValue.value = restrictDigitInput(event, 9);
}

const statusConfig = computed(() => {
    if (!trackedTicket.value) {
        return null;
    }

    const map = {
        waiting: {
            color: 'amber',
            icon: Clock,
            title: 'أنت في قائمة الانتظار',
            hint: 'يرجى الانتظار حتى يتم نداؤك',
        },
        serving: {
            color: 'blue',
            icon: BellRing,
            title: 'حان دورك الآن!',
            hint: 'توجّه إلى الشباك فوراً',
        },
        completed: {
            color: 'green',
            icon: CheckCircle2,
            title: 'تمت خدمتك',
            hint: 'شكراً لزيارتك',
        },
        cancelled: {
            color: 'red',
            icon: XCircle,
            title: 'تم إلغاء التذكرة',
            hint: 'يرجى مراجعة الموظف',
        },
        absent: {
            color: 'orange',
            icon: XCircle,
            title: 'تم نداؤك ولم تحضر',
            hint: 'توجّه للموظف لإرجاعك للطابور',
        },
    };

    return map[trackedTicket.value.status] ?? map.waiting;
});

async function trackTicket(silent = false) {
    fieldError.value = '';

    if (searchType.value === 'national_id' && !/^\d{14}$/.test(searchValue.value)) {
        fieldError.value = 'يجب أن يتكون الرقم القومي من 14 رقمًا.';
        return;
    }

    if (searchType.value === 'order_number' && !/^\d{9}$/.test(searchValue.value)) {
        fieldError.value = 'يجب أن يتكون رقم الطلب من 9 أرقام.';
        return;
    }

    if (searchType.value === 'seat_number' && !/^\d{7,9}$/.test(searchValue.value)) {
        fieldError.value = 'يجب أن يتكون رقم الجلوس من 7 إلى 9 أرقام.';
        return;
    }

    if (!silent) {
        queueStore.loading = true;
    } else {
        isRefreshing.value = true;
    }

    try {
        const payload = searchType.value === 'national_id'
            ? { national_id: searchValue.value }
            : searchType.value === 'seat_number'
                ? { seat_number: searchValue.value }
                : { order_number: searchValue.value.trim() };

        trackedTicket.value = await queueStore.trackTicket(payload);
    } catch {
        trackedTicket.value = null;
        fieldError.value = queueStore.error;
    } finally {
        queueStore.loading = false;
        isRefreshing.value = false;
    }
}

function onQueueEvent() {
    if (trackedTicket.value) {
        trackTicket(true);
    }
}

let unsubscribeEcho = null;
let stopAutoRefresh = null;

onMounted(() => {
    queueStore.fetchPublicStatus();

    unsubscribeEcho = queueStore.subscribeEcho({
        TicketIssued: onQueueEvent,
        TicketCalled: onQueueEvent,
        TicketCompleted: onQueueEvent,
        TicketAbsent: onQueueEvent,
        TicketRestored: onQueueEvent,
        TicketDeleted: (event) => {
            if (trackedTicket.value?.id === event.ticket_id) {
                trackedTicket.value = null;
                fieldError.value = 'تم حذف هذا الطلب.';
            }
        },
        QueueDayReset: () => {
            trackedTicket.value = null;
            fieldError.value = 'تم فتح يوم جديد. تذكرتك أصبحت في الأرشيف.';
        },
    });

    stopAutoRefresh = queueStore.startAutoRefresh(async () => {
        if (trackedTicket.value) {
            await trackTicket(true);
        }
        await queueStore.fetchPublicStatus({ silent: true });
    });
});

onUnmounted(() => {
    unsubscribeEcho?.();
    stopAutoRefresh?.();
});

watch(searchType, () => {
    searchValue.value = '';
    fieldError.value = '';
});
</script>

<template>
    <AppNavbar
        title="متابعة التذكرة"
        subtitle="اعرف مكانك في الطابور"
        page-class="bg-gradient-to-br from-indigo-50 via-white to-purple-50"
    >
        <main class="mx-auto w-full max-w-3xl space-y-6 px-3 py-6 sm:px-6 sm:py-10">
            <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-xl sm:p-8">
                <h2 class="mb-6 text-center text-2xl font-bold text-slate-800">ابحث عن تذكرتك</h2>

                <div class="mb-6 grid grid-cols-3 gap-1 rounded-2xl bg-slate-100 p-1">
                    <button
                        type="button"
                        class="rounded-xl py-2.5 text-xs font-semibold transition sm:text-sm"
                        :class="searchType === 'national_id' ? 'bg-white text-indigo-700 shadow' : 'text-slate-500'"
                        @click="searchType = 'national_id'"
                    >
                        بالرقم القومي
                    </button>
                    <button
                        type="button"
                        class="rounded-xl py-2.5 text-xs font-semibold transition sm:text-sm"
                        :class="searchType === 'order_number' ? 'bg-white text-indigo-700 shadow' : 'text-slate-500'"
                        @click="searchType = 'order_number'"
                    >
                        برقم الطلب
                    </button>
                    <button
                        type="button"
                        class="rounded-xl py-2.5 text-xs font-semibold transition sm:text-sm"
                        :class="searchType === 'seat_number' ? 'bg-white text-indigo-700 shadow' : 'text-slate-500'"
                        @click="searchType = 'seat_number'"
                    >
                        برقم الجلوس
                    </button>
                </div>

                <form class="space-y-5" @submit.prevent="trackTicket()">
                    <div v-if="searchType === 'national_id'">
                        <label class="mb-2 flex items-center gap-2 text-sm font-semibold text-slate-700">
                            <Hash class="h-4 w-4" />
                            الرقم القومي
                        </label>
                        <input
                            :value="searchType === 'national_id' ? searchValue : ''"
                            inputmode="numeric"
                            type="text"
                            maxlength="14"
                            pattern="[0-9]*"
                            autocomplete="off"
                            class="w-full rounded-2xl border border-slate-200 px-5 py-4 text-lg tracking-widest outline-none focus:border-indigo-500 focus:ring-4 focus:ring-indigo-100"
                            placeholder="14 رقم"
                            @input="onNationalIdInput"
                        />
                    </div>

                    <div v-else-if="searchType === 'order_number'">
                        <label class="mb-2 flex items-center gap-2 text-sm font-semibold text-slate-700">
                            <FileText class="h-4 w-4" />
                            رقم الطلب
                        </label>
                        <input
                            :value="searchValue"
                            inputmode="numeric"
                            type="text"
                            maxlength="9"
                            pattern="[0-9]*"
                            autocomplete="off"
                            class="w-full rounded-2xl border border-slate-200 px-5 py-4 text-lg tracking-widest outline-none focus:border-indigo-500 focus:ring-4 focus:ring-indigo-100"
                            placeholder="9 أرقام"
                            @input="onOrderNumberInput"
                        />
                    </div>

                    <div v-else>
                        <label class="mb-2 flex items-center gap-2 text-sm font-semibold text-slate-700">
                            <Hash class="h-4 w-4" />
                            رقم الجلوس
                        </label>
                        <input
                            :value="searchValue"
                            inputmode="numeric"
                            type="text"
                            maxlength="9"
                            pattern="[0-9]*"
                            autocomplete="off"
                            class="w-full rounded-2xl border border-slate-200 px-5 py-4 text-lg tracking-widest outline-none focus:border-indigo-500 focus:ring-4 focus:ring-indigo-100"
                            placeholder="7 إلى 9 أرقام"
                            @input="onSeatNumberInput"
                        />
                    </div>

                    <p v-if="fieldError" class="rounded-xl bg-red-50 px-4 py-3 text-center text-sm text-red-700">
                        {{ fieldError }}
                    </p>

                    <button
                        type="submit"
                        :disabled="queueStore.loading"
                        class="flex w-full items-center justify-center gap-3 rounded-2xl bg-indigo-600 px-6 py-4 text-lg font-bold text-white hover:bg-indigo-700 disabled:opacity-60"
                    >
                        <Search class="h-5 w-5" />
                        {{ queueStore.loading ? 'جاري البحث...' : 'عرض حالة التذكرة' }}
                    </button>
                </form>
            </div>

            <div
                v-if="trackedTicket && statusConfig"
                class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-xl"
            >
                <div
                    class="px-8 py-6 text-center text-white"
                    :class="{
                        'bg-amber-500': statusConfig.color === 'amber',
                        'bg-blue-600': statusConfig.color === 'blue',
                        'bg-green-600': statusConfig.color === 'green',
                        'bg-red-500': statusConfig.color === 'red',
                        'bg-orange-500': statusConfig.color === 'orange',
                    }"
                >
                    <component :is="statusConfig.icon" class="mx-auto mb-3 h-10 w-10" />
                    <h3 class="text-xl font-bold">{{ statusConfig.title }}</h3>
                    <p class="mt-1 text-sm opacity-90">{{ statusConfig.hint }}</p>
                </div>

                <div class="p-8 text-center">
                    <p class="text-sm text-slate-500">رقم تذكرتك</p>
                    <p class="my-3 text-7xl font-black text-indigo-600" dir="ltr">{{ trackedTicket.ticket_number }}</p>
                    <p class="text-lg font-semibold text-slate-800">{{ trackedTicket.masked_name }}</p>
                    <span
                        class="mt-4 inline-block rounded-full px-4 py-1.5 text-sm font-bold"
                        :class="{
                            'bg-amber-100 text-amber-800': trackedTicket.status === 'waiting',
                            'bg-blue-100 text-blue-800': trackedTicket.status === 'serving',
                            'bg-green-100 text-green-800': trackedTicket.status === 'completed',
                            'bg-red-100 text-red-800': trackedTicket.status === 'cancelled',
                            'bg-orange-100 text-orange-800': trackedTicket.status === 'absent',
                        }"
                    >
                        {{ trackedTicket.status_label }}
                    </span>

                    <div v-if="trackedTicket.status === 'waiting'" class="mt-8 grid grid-cols-2 gap-4">
                        <div class="rounded-2xl bg-amber-50 p-4">
                            <p class="text-sm text-amber-700">ترتيبك</p>
                            <p class="text-3xl font-black text-amber-600">{{ trackedTicket.position_in_queue }}</p>
                        </div>
                        <div class="rounded-2xl bg-slate-50 p-4">
                            <p class="text-sm text-slate-500">أمامك</p>
                            <p class="text-3xl font-black text-slate-700">{{ trackedTicket.people_ahead }}</p>
                            <p class="text-xs text-slate-400">تذكرة</p>
                        </div>
                    </div>

                    <div
                        v-if="trackedTicket.status === 'serving' && (trackedTicket.teller_name || trackedTicket.counter_name)"
                        class="mt-8 rounded-2xl bg-blue-50 p-5"
                    >
                        <p class="text-sm text-blue-600">توجّه إلى</p>
                        <p class="text-3xl font-black text-blue-700">
                            {{ trackedTicket.teller_name || trackedTicket.counter_name }}
                        </p>
                        <p
                            v-if="trackedTicket.teller_name && trackedTicket.counter_name && trackedTicket.teller_name !== trackedTicket.counter_name"
                            class="mt-1 text-sm font-semibold text-blue-600"
                        >
                            {{ trackedTicket.counter_name }}
                        </p>
                    </div>

                    <button
                        class="mt-8 flex w-full items-center justify-center gap-2 rounded-2xl border border-slate-200 py-3 text-sm font-semibold text-slate-600 hover:bg-slate-50"
                        :disabled="isRefreshing"
                        @click="trackTicket(true)"
                    >
                        <RefreshCw class="h-4 w-4" :class="{ 'animate-spin': isRefreshing }" />
                        تحديث الحالة
                    </button>
                </div>
            </div>

            <div
                v-if="!queueStore.isSystemOpen"
                class="rounded-2xl border border-red-200 bg-red-50 px-5 py-4 text-center text-sm text-red-700"
            >
                {{ queueStore.system.closed_message || 'النظام مغلق حالياً' }}
            </div>
            <div
                v-else-if="!queueStore.isDayOpen"
                class="rounded-2xl border border-amber-200 bg-amber-50 px-5 py-4 text-center text-sm text-amber-800"
            >
                {{ queueStore.system.day_ended_message || 'انتهى استقبال الطلبات اليوم' }}
            </div>
        </main>
    </AppNavbar>
</template>
