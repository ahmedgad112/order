<script setup>
import { Trash2 } from 'lucide-vue-next';
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

const emit = defineEmits(['delete']);
const authStore = useAuthStore();

function requestDelete() {
    const label = props.ticket.full_name || props.ticket.order_number || props.ticket.ticket_number;

    if (!confirm(`هل تريد حذف طلب "${label}" (تذكرة ${props.ticket.ticket_number})؟`)) {
        return;
    }

    emit('delete', props.ticket);
}
</script>

<template>
    <button
        v-if="authStore.canDeleteTickets"
        type="button"
        class="flex w-full items-center justify-center gap-1.5 rounded-xl border border-red-200 bg-white px-3 py-2.5 text-sm font-bold text-red-700 hover:bg-red-50 disabled:opacity-60"
        :disabled="busy"
        @click="requestDelete"
    >
        <Trash2 class="h-4 w-4" />
        {{ busy ? 'جاري الحذف...' : 'حذف الطلب' }}
    </button>
</template>
