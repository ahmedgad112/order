<script setup>
import { ref } from 'vue';
import { Image } from 'lucide-vue-next';
import { axios } from '../bootstrap';

const props = defineProps({
    ticket: {
        type: Object,
        required: true,
    },
});

const loading = ref(false);
const error = ref('');

async function openDocument() {
    if (!props.ticket.document_url) {
        return;
    }

    loading.value = true;
    error.value = '';

    try {
        const { data } = await axios.get(props.ticket.document_url, {
            responseType: 'blob',
        });
        const url = URL.createObjectURL(data);
        window.open(url, '_blank', 'noopener');
        setTimeout(() => URL.revokeObjectURL(url), 60_000);
    } catch {
        error.value = 'تعذر فتح المستند.';
    } finally {
        loading.value = false;
    }
}
</script>

<template>
    <div v-if="ticket.has_document" class="flex flex-col items-end gap-1">
        <button
            type="button"
            class="inline-flex items-center gap-1.5 text-sm font-semibold text-indigo-700 hover:text-indigo-900"
            :disabled="loading"
            @click="openDocument"
        >
            <Image class="h-4 w-4" />
            {{ loading ? 'جاري الفتح...' : 'عرض المستند' }}
        </button>
        <p v-if="error" class="text-xs text-red-600">{{ error }}</p>
    </div>
    <span v-else class="text-slate-400">—</span>
</template>
