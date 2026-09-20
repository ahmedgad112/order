<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { Check, ChevronDown } from 'lucide-vue-next';

const props = defineProps({
    modelValue: {
        type: Array,
        default: () => [],
    },
    options: {
        type: Array,
        default: () => [],
    },
    placeholder: {
        type: String,
        default: 'اختر أنواع الطلب',
    },
    emptyText: {
        type: String,
        default: 'لا توجد أنواع طلب متاحة',
    },
    summarySuffix: {
        type: String,
        default: 'أنواع',
    },
    disabled: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['update:modelValue']);

const open = ref(false);
const root = ref(null);

const selectedValues = computed(() => props.modelValue ?? []);

const summary = computed(() => {
    const labels = props.options
        .filter((option) => selectedValues.value.includes(option.value))
        .map((option) => option.label);

    if (!labels.length) {
        return props.placeholder;
    }

    if (labels.length <= 2) {
        return labels.join('، ');
    }

    return `${labels.length} ${props.summarySuffix}`;
});

function isSelected(value) {
    return selectedValues.value.includes(value);
}

function toggleOption(value) {
    if (props.disabled) {
        return;
    }

    const next = isSelected(value)
        ? selectedValues.value.filter((item) => item !== value)
        : [...selectedValues.value, value];

    emit('update:modelValue', next);
}

function toggleOpen() {
    if (props.disabled) {
        return;
    }

    open.value = !open.value;
}

function onDocumentClick(event) {
    if (!root.value?.contains(event.target)) {
        open.value = false;
    }
}

onMounted(() => {
    document.addEventListener('click', onDocumentClick);
});

onUnmounted(() => {
    document.removeEventListener('click', onDocumentClick);
});
</script>

<template>
    <div ref="root" class="relative">
        <button
            type="button"
            class="flex w-full items-center justify-between gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-right text-sm font-semibold outline-none focus:border-indigo-500 focus:ring-4 focus:ring-indigo-100 disabled:cursor-not-allowed disabled:opacity-60"
            :class="selectedValues.length ? 'text-slate-800' : 'text-slate-400'"
            :disabled="disabled"
            @click="toggleOpen"
        >
            <span class="min-w-0 truncate">{{ summary }}</span>
            <ChevronDown class="h-4 w-4 shrink-0 text-slate-400" :class="{ 'rotate-180': open }" />
        </button>

        <div
            v-if="open"
            class="absolute inset-x-0 top-full z-30 mt-1 overflow-hidden rounded-xl border border-slate-200 bg-white py-1 shadow-lg"
        >
            <button
                v-for="option in options"
                :key="option.value"
                type="button"
                class="flex w-full items-center justify-between gap-3 px-3 py-2.5 text-sm hover:bg-slate-50"
                :class="isSelected(option.value) ? 'font-semibold text-indigo-700' : 'text-slate-700'"
                @click="toggleOption(option.value)"
            >
                <span>{{ option.label }}</span>
                <Check v-if="isSelected(option.value)" class="h-4 w-4 text-indigo-600" />
            </button>
            <p v-if="!options.length" class="px-3 py-2.5 text-sm text-slate-400">
                {{ emptyText }}
            </p>
        </div>
    </div>
</template>
