<script setup>
import { RotateCcw } from 'lucide-vue-next';
import { useAuthStore } from '../stores/authStore';

const props = defineProps({
    ticket: {
        type: Object,
        required: true,
    },
    busy: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['restore']);
const authStore = useAuthStore();

function requestRestore() {
    const label = props.ticket.full_name || props.ticket.order_number || props.ticket.ticket_number;

    if (!confirm(`هل تريد إرجاع الطلب الملغى "${label}" (تذكرة ${props.ticket.ticket_number}) لقائمة الانتظار؟`)) {
        return;
    }

    emit('restore', props.ticket);
}
</script>

<template>
    <button
        v-if="authStore.isSuperAdmin && ticket.status === 'cancelled'"
        type="button"
        class="flex w-full items-center justify-center gap-1.5 rounded-xl border border-emerald-200 bg-white px-3 py-2.5 text-sm font-bold text-emerald-700 hover:bg-emerald-50 disabled:opacity-60"
        :disabled="busy"
        @click="requestRestore"
    >
        <RotateCcw class="h-4 w-4" />
        {{ busy ? 'جاري الإرجاع...' : 'إرجاع الطلب الملغى' }}
    </button>
</template>
