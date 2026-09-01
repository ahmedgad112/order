<script setup>
import { ref } from 'vue';
import { Eye, EyeOff } from 'lucide-vue-next';

defineProps({
    modelValue: {
        type: String,
        default: '',
    },
    required: {
        type: Boolean,
        default: false,
    },
    minlength: {
        type: [Number, String],
        default: undefined,
    },
    placeholder: {
        type: String,
        default: '',
    },
    autocomplete: {
        type: String,
        default: 'current-password',
    },
    inputClass: {
        type: String,
        default: 'w-full rounded-xl border border-slate-200 px-4 py-3 outline-none focus:border-blue-500 focus:ring-4 focus:ring-blue-100',
    },
});

defineEmits(['update:modelValue']);

const visible = ref(false);
</script>

<template>
    <div class="relative">
        <input
            :value="modelValue"
            :type="visible ? 'text' : 'password'"
            :required="required"
            :minlength="minlength"
            :placeholder="placeholder"
            :autocomplete="autocomplete"
            :class="[inputClass, 'pe-12']"
            @input="$emit('update:modelValue', $event.target.value)"
        />
        <button
            type="button"
            class="absolute inset-y-0 end-0 flex items-center px-3 text-slate-400 hover:text-slate-700"
            :title="visible ? 'إخفاء كلمة المرور' : 'إظهار كلمة المرور'"
            :aria-label="visible ? 'إخفاء كلمة المرور' : 'إظهار كلمة المرور'"
            @click="visible = !visible"
        >
            <EyeOff v-if="visible" class="h-5 w-5" />
            <Eye v-else class="h-5 w-5" />
        </button>
    </div>
</template>
