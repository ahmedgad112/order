<script setup>
import { computed } from 'vue';
import {
    Banknote,
    CheckCircle2,
    FileText,
    FolderCheck,
    FolderDown,
    Layers,
    ScanFace,
    Stethoscope,
    UserCheck,
} from 'lucide-vue-next';

const props = defineProps({
    ticket: { type: Object, required: true },
    busyId: { type: [Number, String], default: null },
    busyStep: { type: String, default: null },
    systemOpen: { type: Boolean, default: true },
    compact: { type: Boolean, default: false },
    allowedSteps: { type: Array, default: null },
});

const emit = defineEmits(['mark']);

const stepMeta = {
    entered: {
        label: 'طلب دخول',
        doneLabel: 'تم الدخول',
        icon: UserCheck,
        buttonClass: 'bg-blue-600 hover:bg-blue-700 text-white',
        doneClass: 'text-blue-700',
        requiresSystem: true,
    },
    paid: {
        label: 'دفع',
        doneLabel: 'تم الدفع',
        icon: Banknote,
        buttonClass: 'bg-cyan-600 hover:bg-cyan-700 text-white',
        doneClass: 'text-cyan-700',
    },
    file_withdrawn: {
        label: 'سحب ملف',
        doneLabel: 'تم السحب',
        icon: FolderDown,
        buttonClass: 'bg-orange-600 hover:bg-orange-700 text-white',
        doneClass: 'text-orange-700',
    },
    documents_reviewed: {
        label: 'مراجعة ورق',
        doneLabel: 'تمت المراجعة',
        icon: FileText,
        buttonClass: 'bg-indigo-600 hover:bg-indigo-700 text-white',
        doneClass: 'text-indigo-700',
    },
    medical_checked: {
        label: 'كشف طبي',
        doneLabel: 'تم الكشف',
        icon: Stethoscope,
        buttonClass: 'bg-teal-600 hover:bg-teal-700 text-white',
        doneClass: 'text-teal-700',
    },
    face_printed: {
        label: 'بصمة وجه',
        doneLabel: 'تم البصمة',
        icon: ScanFace,
        buttonClass: 'bg-violet-600 hover:bg-violet-700 text-white',
        doneClass: 'text-violet-700',
    },
    file_delivered: {
        label: 'تسليم ملف',
        doneLabel: 'تم التسليم',
        icon: FolderCheck,
        buttonClass: 'bg-green-600 hover:bg-green-700 text-white',
        doneClass: 'text-green-700',
    },
    completed: {
        label: 'اكتمال',
        doneLabel: 'مكتمل',
        icon: CheckCircle2,
        buttonClass: 'bg-emerald-700 hover:bg-emerald-800 text-white',
        doneClass: 'text-emerald-700',
    },
};

const isClosed = computed(() => ['cancelled', 'absent'].includes(props.ticket.status));
const isBusyTicket = computed(() => props.busyId === props.ticket.id);

const steps = computed(() => {
    const pipeline = Array.isArray(props.ticket.process_pipeline)
        ? props.ticket.process_pipeline
        : null;

    if (!pipeline) {
        return [];
    }

    return pipeline
        .filter((item) => {
            if (!Array.isArray(props.allowedSteps)) {
                return true;
            }

            return props.allowedSteps.includes(item.key)
                || (item.system_key && props.allowedSteps.includes(item.system_key));
        })
        .map((item) => {
            const metaKey = item.system_key ?? item.key;
            const meta = stepMeta[metaKey] ?? {
                label: item.label,
                doneLabel: `تم — ${item.label}`,
                icon: Layers,
                buttonClass: 'bg-slate-700 hover:bg-slate-800 text-white',
                doneClass: 'text-slate-700',
            };

            return {
                key: item.key,
                systemKey: item.system_key,
                isCustom: item.is_custom,
                label: item.label || meta.label,
                doneLabel: meta.doneLabel,
                icon: meta.icon,
                buttonClass: meta.buttonClass,
                doneClass: meta.doneClass,
                requiresSystem: Boolean(meta.requiresSystem),
                done: Boolean(item.done),
            };
        });
});

function canMark(step, index) {
    if (isClosed.value || props.ticket.status === 'completed') {
        return false;
    }

    if (step.done) {
        return false;
    }

    if (step.requiresSystem && !props.systemOpen) {
        return false;
    }

    if (index > 0 && !steps.value[index - 1]?.done) {
        return false;
    }

    return true;
}

function isBusy(step) {
    return isBusyTicket.value && props.busyStep === step.key;
}
</script>

<template>
    <div
        class="flex flex-wrap"
        :class="compact ? 'gap-1.5' : 'gap-2'"
    >
        <template v-for="(step, index) in steps" :key="step.key">
            <button
                v-if="canMark(step, index)"
                type="button"
                class="inline-flex items-center justify-center font-bold disabled:opacity-60"
                :class="[
                    step.buttonClass,
                    compact
                        ? 'gap-1 rounded-lg px-2 py-1.5 text-xs'
                        : 'min-w-[8.5rem] grow gap-1.5 rounded-xl px-3 py-2.5 text-sm',
                ]"
                :disabled="isBusyTicket"
                @click="emit('mark', step.key)"
            >
                <component :is="step.icon" :class="compact ? 'h-3.5 w-3.5' : 'h-4 w-4'" />
                {{ isBusy(step) ? 'جاري...' : step.label }}
            </button>
            <span
                v-else-if="step.done"
                class="inline-flex items-center font-semibold"
                :class="[
                    step.doneClass,
                    compact ? 'gap-1 px-1 text-xs' : 'gap-1.5 px-2 text-sm',
                ]"
            >
                <CheckCircle2 :class="compact ? 'h-3.5 w-3.5' : 'h-4 w-4'" />
                {{ step.doneLabel }}
            </span>
            <span
                v-else
                class="inline-flex items-center text-slate-400"
                :class="compact ? 'px-1 text-xs' : 'px-2 text-sm'"
            >
                {{ step.label }}
            </span>
        </template>
    </div>
</template>
