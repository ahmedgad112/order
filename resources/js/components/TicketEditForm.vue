<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { Check, Pencil, X } from 'lucide-vue-next';
import { useAuthStore } from '../stores/authStore';
import { useQueueStore } from '../stores/queueStore';

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
const queueStore = useQueueStore();
const editing = ref(false);
const form = reactive({
    full_name: '',
    national_id: '',
    request_type: '',
    college: '',
    order_number: '',
    department: '',
    seat_number: '',
});

const requestTypes = computed(() => {
    const types = queueStore.system.request_types ?? [];

    if (types.length) {
        return types;
    }

    return [
        { value: 'nomination_card', label: 'حاصل على بطاقة ترشيح', college_mode: 'select', college_label: 'الكلية الواردة في بطاقة الترشيح' },
        { value: 'direct_application', label: 'تقديم مباشر', college_mode: 'text', college_label: 'الكلية المراد الالتحاق بها' },
        { value: 'transfer', label: 'تحويل (مناظر / غير مناظر)', college_mode: 'text', college_label: 'الكلية المراد الالتحاق بها' },
    ];
});

const colleges = computed(() => queueStore.system.colleges ?? []);
const faculties = computed(() => (
    (queueStore.system.faculties ?? []).length
        ? queueStore.system.faculties
        : [
            { value: 'industry_energy', label: 'صناعة وطاقة' },
            { value: 'health_sciences', label: 'علوم صحية' },
        ]
));
const isCurrentStudent = computed(() => props.ticket.student_kind === 'current_student');

const selectedRequestType = computed(() => (
    requestTypes.value.find((type) => type.value === form.request_type) ?? null
));

watch(
    () => props.busy,
    (busy, wasBusy) => {
        if (wasBusy && !busy) {
            editing.value = false;
        }
    },
);

watch(() => form.request_type, (type, previous) => {
    if (!editing.value || !previous || type === previous || isCurrentStudent.value) {
        return;
    }

    if (type === 'nomination_card') {
        if (!colleges.value.some((college) => college.value === form.college)) {
            form.college = '';
        }

        return;
    }

    const match = colleges.value.find((college) => college.value === form.college);
    if (match) {
        form.college = match.label;
    }
});

function startEdit() {
    form.full_name = props.ticket.full_name ?? '';
    form.national_id = props.ticket.national_id ?? '';
    form.request_type = props.ticket.request_type ?? '';
    form.college = props.ticket.college ?? '';
    form.order_number = props.ticket.order_number ?? '';
    form.department = props.ticket.department ?? '';
    form.seat_number = props.ticket.seat_number ?? '';
    editing.value = true;
}

function cancelEdit() {
    editing.value = false;
}

function onNationalIdInput(event) {
    form.national_id = event.target.value.replace(/\D/g, '').slice(0, 14);
    event.target.value = form.national_id;
}

function onOrderNumberInput(event) {
    form.order_number = event.target.value.replace(/\D/g, '').slice(0, 9);
    event.target.value = form.order_number;
}

function onSeatNumberInput(event) {
    form.seat_number = event.target.value.replace(/\D/g, '').slice(0, 7);
    event.target.value = form.seat_number;
}

function submitEdit() {
    if (isCurrentStudent.value) {
        emit('save', {
            ...props.ticket,
            full_name: form.full_name.trim(),
            college: form.college.trim(),
            department: form.department.trim(),
            seat_number: form.seat_number.trim(),
        });
        return;
    }

    emit('save', {
        ...props.ticket,
        full_name: form.full_name.trim(),
        national_id: form.national_id.trim(),
        request_type: form.request_type,
        college: form.college.trim(),
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
            <template v-if="isCurrentStudent">
                <label class="block text-xs font-semibold text-slate-600">
                    الكلية
                    <select
                        v-model="form.college"
                        class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100"
                        required
                    >
                        <option value="" disabled>اختر الكلية</option>
                        <option v-for="faculty in faculties" :key="faculty.value" :value="faculty.value">
                            {{ faculty.label }}
                        </option>
                    </select>
                </label>
                <label class="block text-xs font-semibold text-slate-600">
                    القسم
                    <input
                        v-model="form.department"
                        type="text"
                        class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100"
                        required
                        minlength="2"
                    />
                </label>
                <label class="block text-xs font-semibold text-slate-600">
                    رقم الجلوس
                    <input
                        :value="form.seat_number"
                        type="text"
                        inputmode="numeric"
                        maxlength="7"
                        pattern="[0-9]*"
                        autocomplete="off"
                        class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 font-mono text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100"
                        required
                        @input="onSeatNumberInput"
                    />
                </label>
            </template>
            <template v-else>
            <label class="block text-xs font-semibold text-slate-600">
                الرقم القومي
                <input
                    :value="form.national_id"
                    type="text"
                    inputmode="numeric"
                    maxlength="14"
                    pattern="[0-9]*"
                    autocomplete="off"
                    class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 font-mono text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100"
                    required
                    @input="onNationalIdInput"
                />
            </label>
            <label class="block text-xs font-semibold text-slate-600">
                نوع الطلب
                <select
                    v-model="form.request_type"
                    class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100"
                    required
                >
                    <option value="" disabled>اختر نوع الطلب</option>
                    <option v-for="type in requestTypes" :key="type.value" :value="type.value">
                        {{ type.label }}
                    </option>
                </select>
            </label>
            <label v-if="selectedRequestType" class="block text-xs font-semibold text-slate-600">
                {{ selectedRequestType.college_label }}
                <select
                    v-if="selectedRequestType.college_mode === 'select'"
                    v-model="form.college"
                    class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100"
                    required
                >
                    <option value="" disabled>اختر الكلية</option>
                    <option v-for="college in colleges" :key="college.value" :value="college.value">
                        {{ college.label }}
                    </option>
                </select>
                <input
                    v-else
                    v-model="form.college"
                    type="text"
                    class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100"
                    required
                    minlength="3"
                    placeholder="اكتب اسم الكلية"
                />
            </label>
            <label class="block text-xs font-semibold text-slate-600">
                رقم الطلب
                <input
                    :value="form.order_number"
                    type="text"
                    inputmode="numeric"
                    maxlength="9"
                    pattern="[0-9]*"
                    autocomplete="off"
                    class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100"
                    required
                    @input="onOrderNumberInput"
                />
            </label>
            </template>
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
