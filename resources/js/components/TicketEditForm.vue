<script setup>
import { reactive, ref, watch } from 'vue';
import { Check, Pencil, X } from 'lucide-vue-next';
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

const emit = defineEmits(['save']);
const authStore = useAuthStore();
const editing = ref(false);
const form = reactive({
    full_name: '',
    national_id: '',
    order_number: '',
});

watch(
    () => props.busy,
    (busy, wasBusy) => {
        if (wasBusy && !busy) {
            editing.value = false;
        }
    },
);

function startEdit() {
    form.full_name = props.ticket.full_name ?? '';
    form.national_id = props.ticket.national_id ?? '';
    form.order_number = props.ticket.order_number ?? '';
    editing.value = true;
}

function cancelEdit() {
    editing.value = false;
}

function submitEdit() {
    emit('save', {
        ...props.ticket,
        full_name: form.full_name.trim(),
        national_id: form.national_id.trim(),
        order_number: form.order_number.trim(),
    });
}
</script>

<template>
    <div v-if="authStore.canEditTickets">
        <form
            v-if="editing"
            class="space-y-2 rounded-xl border border-indigo-100 bg-white p-3"
            @submit.prevent="submitEdit"
        >
            <label class="block text-xs font-semibold text-slate-600">
                الاسم
                <input
                    v-model="form.full_name"
                    type="text"
                    class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100"
                    required
                    minlength="3"
                />
            </label>
            <label class="block text-xs font-semibold text-slate-600">
                الرقم القومي
                <input
                    v-model="form.national_id"
                    type="text"
                    inputmode="numeric"
                    maxlength="14"
                    class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 font-mono text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100"
                    required
                />
            </label>
            <label class="block text-xs font-semibold text-slate-600">
                رقم الطلب
                <input
                    v-model="form.order_number"
                    type="text"
                    class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100"
                    required
                />
            </label>
            <div class="flex gap-2">
                <button
                    type="submit"
                    class="flex flex-1 items-center justify-center gap-1 rounded-lg bg-indigo-600 px-3 py-2 text-sm font-bold text-white hover:bg-indigo-700 disabled:opacity-60"
                    :disabled="busy"
                >
                    <Check class="h-4 w-4" />
                    {{ busy ? 'جاري الحفظ...' : 'حفظ' }}
                </button>
                <button
                    type="button"
                    class="flex items-center justify-center rounded-lg border border-slate-200 px-3 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-50"
                    :disabled="busy"
                    @click="cancelEdit"
                >
                    <X class="h-4 w-4" />
                </button>
            </div>
        </form>
        <button
            v-else
            type="button"
            class="flex w-full items-center justify-center gap-1.5 rounded-xl border border-indigo-200 bg-white px-3 py-2.5 text-sm font-bold text-indigo-700 hover:bg-indigo-50 disabled:opacity-60"
            @click="startEdit"
        >
            <Pencil class="h-4 w-4" />
            تعديل الطلب
        </button>
    </div>
</template>
