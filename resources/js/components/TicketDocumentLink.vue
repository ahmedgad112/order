<script setup>
import { onUnmounted, ref, watch } from 'vue';
import { Image, X } from 'lucide-vue-next';
import { axios } from '../bootstrap';

const props = defineProps({
    ticket: {
        type: Object,
        required: true,
    },
    preview: {
        type: Boolean,
        default: false,
    },
});

const loading = ref(false);
const error = ref('');
const previewUrl = ref('');
const showModal = ref(false);

function revokePreview() {
    if (previewUrl.value) {
        URL.revokeObjectURL(previewUrl.value);
        previewUrl.value = '';
    }
}

async function loadDocument() {
    if (!props.ticket.document_url) {
        return null;
    }

    loading.value = true;
    error.value = '';

    try {
        const { data } = await axios.get(props.ticket.document_url, {
            responseType: 'blob',
        });
        revokePreview();
        previewUrl.value = URL.createObjectURL(data);

        return previewUrl.value;
    } catch {
        error.value = 'تعذر فتح المستند.';

        return null;
    } finally {
        loading.value = false;
    }
}

async function openDocument() {
    const url = previewUrl.value || await loadDocument();

    if (!url) {
        return;
    }

    showModal.value = true;
}

function closeModal() {
    showModal.value = false;
}

watch(
    () => [props.ticket.id, props.ticket.document_url, props.preview],
    ([, url, shouldPreview]) => {
        if (shouldPreview && url) {
            loadDocument();
        }
    },
    { immediate: true },
);

onUnmounted(() => {
    revokePreview();
});
</script>

<template>
    <div v-if="ticket.has_document" class="flex flex-col gap-2" :class="preview ? 'items-start' : 'items-end'">
        <button
            v-if="preview && previewUrl"
            type="button"
            class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm"
            @click="openDocument"
        >
            <img
                :src="previewUrl"
                alt="المستند المرفوع"
                class="h-28 w-40 object-cover"
            />
        </button>
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

        <Teleport to="body">
            <div
                v-if="showModal && previewUrl"
                class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/70 p-4"
                @click.self="closeModal"
            >
                <div class="relative max-h-[90vh] w-full max-w-4xl overflow-hidden rounded-3xl bg-white shadow-2xl">
                    <div class="flex items-center justify-between border-b border-slate-200 px-5 py-3">
                        <p class="text-sm font-bold text-slate-800">
                            {{ ticket.document_kind_label ?? 'المستند المرفوع' }}
                        </p>
                        <button
                            type="button"
                            class="rounded-full p-1 text-slate-500 hover:bg-slate-100 hover:text-slate-800"
                            @click="closeModal"
                        >
                            <X class="h-5 w-5" />
                        </button>
                    </div>
                    <div class="max-h-[80vh] overflow-auto bg-slate-100 p-4">
                        <img
                            :src="previewUrl"
                            alt="المستند المرفوع"
                            class="mx-auto max-h-[75vh] w-auto rounded-xl object-contain"
                        />
                    </div>
                </div>
            </div>
        </Teleport>
    </div>
    <span v-else class="text-slate-400">—</span>
</template>
