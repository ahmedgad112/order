<script setup>
import { computed, ref, watch } from 'vue';
import { Plus, X } from 'lucide-vue-next';
import { useAuthStore } from '../stores/authStore';
import { useQueueStore } from '../stores/queueStore';

const props = defineProps({
    requireName: {
        type: Boolean,
        default: true,
    },
});

const emit = defineEmits(['issued', 'close']);

const authStore = useAuthStore();
const queueStore = useQueueStore();

const form = ref(emptyForm());
const fieldErrors = ref({});
const saving = ref(false);

const availableTypes = computed(() => {
    const enabledRequestTypes = (queueStore.system.request_types ?? [])
        .filter((type) => type.enabled)
        .map((type) => ({ value: type.value, label: type.label }));

    const kinds = queueStore.system.student_kinds ?? [];
    const currentStudentEnabled = kinds.length
        ? kinds.some((kind) => kind.value === 'current_student' && kind.enabled !== false)
        : true;
    const newStudentEnabled = kinds.length
        ? kinds.some((kind) => kind.value === 'new_student' && kind.enabled !== false)
        : true;

    const types = [];

    if (newStudentEnabled) {
        types.push(...enabledRequestTypes);
    }

    if (currentStudentEnabled) {
        types.push({ value: 'current_student', label: 'طالب حالي (فرقة ثانية)' });
    }

    if (!authStore.isTeller) {
        return types;
    }

    const assigned = (authStore.user?.queue_lanes ?? []).map((lane) => lane.value ?? lane);

    return types.filter((type) => assigned.includes(type.value));
});

const canIssue = computed(() => queueStore.isAcceptingTickets && availableTypes.value.length > 0);

watch(availableTypes, (types) => {
    if (form.value.request_type && !types.some((type) => type.value === form.value.request_type)) {
        form.value.request_type = '';
    }
});

function emptyForm() {
    return {
        full_name: '',
        order_number: '',
        request_type: '',
        count: '1',
    };
}

function restrictOrderNumber(event) {
    form.value.order_number = event.target.value.replace(/\D/g, '').slice(0, 9);
}

function validateClient() {
    const errors = {};

    if (props.requireName && (!form.value.full_name || form.value.full_name.trim().length < 3)) {
        errors.full_name = 'اسم الطالب مطلوب (3 أحرف على الأقل).';
    }

    if (props.requireName && !/^\d{9}$/.test(form.value.order_number)) {
        errors.order_number = 'يجب أن يتكون رقم الطلب من 9 أرقام.';
    }

    if (!form.value.request_type) {
        errors.request_type = 'يجب اختيار نوع الطلب.';
    }

    if (!props.requireName) {
        const count = Number.parseInt(form.value.count, 10);

        if (!Number.isInteger(count) || count < 1) {
            errors.count = 'يجب إصدار دور واحد على الأقل.';
        } else if (count > 50) {
            errors.count = 'يمكن إصدار 50 دور كحد أقصى في المرة الواحدة.';
        }
    }

    fieldErrors.value = errors;

    return Object.keys(errors).length === 0;
}

async function submitForm() {
    if (!validateClient()) {
        return;
    }

    saving.value = true;
    fieldErrors.value = {};

    try {
        const payload = {
            request_type: form.value.request_type,
        };

        if (props.requireName) {
            payload.full_name = form.value.full_name.trim();
            payload.order_number = form.value.order_number;
        } else {
            payload.count = Number.parseInt(form.value.count, 10) || 1;
        }

        const result = await queueStore.issueTicket(payload);

        emit('issued', result);
        form.value = emptyForm();
    } catch (err) {
        const errors = err.response?.data?.errors;
        if (errors) {
            fieldErrors.value = Object.fromEntries(
                Object.entries(errors).map(([key, messages]) => [key, messages[0]]),
            );
        }

        fieldErrors.value.general = queueStore.error
            ?? err.response?.data?.message
            ?? 'تعذر إصدار الدور.';
    } finally {
        saving.value = false;
    }
}
</script>

<template>
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4" @click.self="emit('close')">
        <div class="w-full max-w-lg rounded-3xl bg-white p-6 shadow-2xl">
            <div class="mb-6 flex items-start justify-between gap-3">
                <div>
                    <h3 class="flex items-center gap-2 text-xl font-bold text-slate-900">
                        <Plus class="h-5 w-5 text-indigo-600" />
                        {{ requireName ? 'دور جديد' : 'دور بالنوع' }}
                    </h3>
                    <p class="mt-1 text-sm text-slate-500">
                        {{ requireName ? 'اسم الطالب ورقم الطلب والنوع فقط' : 'نوع الطلب وعدد الأدوار — الأرقام بتتسجل وتتطبع ورا بعض' }}
                    </p>
                </div>
                <button
                    type="button"
                    class="rounded-xl p-2 text-slate-400 hover:bg-slate-100 hover:text-slate-700"
                    @click="emit('close')"
                >
                    <X class="h-5 w-5" />
                </button>
            </div>

            <div
                v-if="!queueStore.isAcceptingTickets"
                class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-semibold text-amber-800"
            >
                النظام لا يستقبل أدوار جديدة حالياً.
            </div>
            <div
                v-else-if="!availableTypes.length"
                class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-semibold text-amber-800"
            >
                لا يوجد نوع طلب متاح لحسابك. تواصل مع الإدارة.
            </div>

            <form v-else class="space-y-4" @submit.prevent="submitForm">
                <div v-if="requireName">
                    <label class="mb-1 block text-sm font-semibold text-slate-700">اسم الطالب</label>
                    <input
                        v-model="form.full_name"
                        type="text"
                        required
                        minlength="3"
                        class="w-full rounded-xl border border-slate-200 px-4 py-2.5 outline-none focus:border-indigo-500 focus:ring-4 focus:ring-indigo-100"
                    />
                    <p v-if="fieldErrors.full_name" class="mt-1 text-sm text-red-600">{{ fieldErrors.full_name }}</p>
                </div>

                <div v-if="requireName">
                    <label class="mb-1 block text-sm font-semibold text-slate-700">رقم الطلب</label>
                    <input
                        v-model="form.order_number"
                        type="text"
                        inputmode="numeric"
                        maxlength="9"
                        required
                        class="w-full rounded-xl border border-slate-200 px-4 py-2.5 outline-none focus:border-indigo-500 focus:ring-4 focus:ring-indigo-100"
                        @input="restrictOrderNumber"
                    />
                    <p v-if="fieldErrors.order_number" class="mt-1 text-sm text-red-600">{{ fieldErrors.order_number }}</p>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-semibold text-slate-700">نوع الطلب</label>
                    <select
                        v-model="form.request_type"
                        required
                        class="w-full rounded-xl border border-slate-200 px-4 py-2.5 outline-none focus:border-indigo-500 focus:ring-4 focus:ring-indigo-100"
                    >
                        <option value="" disabled>اختر النوع</option>
                        <option
                            v-for="type in availableTypes"
                            :key="type.value"
                            :value="type.value"
                        >
                            {{ type.label }}
                        </option>
                    </select>
                    <p v-if="fieldErrors.request_type" class="mt-1 text-sm text-red-600">{{ fieldErrors.request_type }}</p>
                </div>

                <div v-if="!requireName">
                    <label class="mb-1 block text-sm font-semibold text-slate-700">عدد الأدوار</label>
                    <input
                        v-model="form.count"
                        type="number"
                        min="1"
                        max="50"
                        required
                        class="w-full rounded-xl border border-slate-200 px-4 py-2.5 outline-none focus:border-indigo-500 focus:ring-4 focus:ring-indigo-100"
                    />
                    <p class="mt-1 text-xs text-slate-500">كل دور بيتسجل في النظام ويتطبع في ورقة لوحده.</p>
                    <p v-if="fieldErrors.count" class="mt-1 text-sm text-red-600">{{ fieldErrors.count }}</p>
                </div>

                <p v-if="fieldErrors.general" class="rounded-xl bg-red-50 px-4 py-3 text-sm text-red-700">
                    {{ fieldErrors.general }}
                </p>

                <div class="flex gap-3 pt-2">
                    <button
                        type="submit"
                        :disabled="saving || !canIssue"
                        class="flex-1 rounded-xl bg-indigo-600 py-3 font-bold text-white hover:bg-indigo-700 disabled:opacity-60"
                    >
                        {{ saving ? 'جاري الإصدار...' : (requireName ? 'إصدار الدور' : 'إصدار وطباعة') }}
                    </button>
                    <button
                        type="button"
                        class="flex-1 rounded-xl border border-slate-200 py-3 font-semibold text-slate-700 hover:bg-slate-50"
                        @click="emit('close')"
                    >
                        إلغاء
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>
