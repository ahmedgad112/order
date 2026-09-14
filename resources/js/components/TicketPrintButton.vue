<script setup>
import { ref } from 'vue';
import { Printer } from 'lucide-vue-next';
import IssuedTicketModal from './IssuedTicketModal.vue';

defineProps({
    ticket: {
        type: Object,
        required: true,
    },
});

const showPreview = ref(false);
</script>

<template>
    <div>
        <button
            type="button"
            class="flex w-full items-center justify-center gap-1.5 rounded-xl bg-blue-600 px-3 py-2.5 text-sm font-bold text-white hover:bg-blue-700"
            @click="showPreview = true"
        >
            <Printer class="h-4 w-4" />
            طباعة التذكرة
        </button>

        <IssuedTicketModal
            v-if="showPreview"
            :ticket="ticket"
            allow-print
            :allow-download="false"
            :show-admission-links="false"
            heading="طباعة التذكرة"
            @close="showPreview = false"
        />
    </div>
</template>
