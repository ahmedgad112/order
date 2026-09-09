<script setup>
import { computed } from 'vue';
import {
    CheckCircle2,
    FileText,
    FolderCheck,
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
});

const emit = defineEmits(['mark']);

const isCurrentStudent = computed(() => props.ticket.student_kind === 'current_student');

const admissionSteps = [
    {
        key: 'entered',
        label: 'طلب دخول',
        doneLabel: 'تم الدخول',
        icon: UserCheck,
        buttonClass: 'bg-blue-600 hover:bg-blue-700 text-white',
        doneClass: 'text-blue-700',
        requiresSystem: true,
    },
    {
        key: 'medical_checked',
        label: 'كشف طبي',
        doneLabel: 'تم الكشف',
        icon: Stethoscope,
        buttonClass: 'bg-teal-600 hover:bg-teal-700 text-white',
        doneClass: 'text-teal-700',
    },
    {
        key: 'face_printed',
        label: 'بصمة وجه',
        doneLabel: 'تم البصمة',
        icon: ScanFace,
        buttonClass: 'bg-violet-600 hover:bg-violet-700 text-white',
        doneClass: 'text-violet-700',
    },
    {
        key: 'file_delivered',
        label: 'تسليم ملف',
        doneLabel: 'تم التسليم',
        icon: FolderCheck,
        buttonClass: 'bg-green-600 hover:bg-green-700 text-white',
        doneClass: 'text-green-700',
    },
    {
        key: 'completed',
        label: 'اكتمال',
        doneLabel: 'مكتمل',
        icon: CheckCircle2,
        buttonClass: 'bg-emerald-700 hover:bg-emerald-800 text-white',
        doneClass: 'text-emerald-700',
    },
];

const currentStudentSteps = [
    {
        key: 'entered',
        label: 'دخول',
        doneLabel: 'تم الدخول',
        icon: UserCheck,
        buttonClass: 'bg-blue-600 hover:bg-blue-700 text-white',
        doneClass: 'text-blue-700',
        requiresSystem: true,
    },
    {
        key: 'documents_reviewed',
        label: 'مراجعة ورق',
        doneLabel: 'تمت المراجعة',
        icon: FileText,
        buttonClass: 'bg-indigo-600 hover:bg-indigo-700 text-white',
        doneClass: 'text-indigo-700',
    },
    {
        key: 'file_delivered',
        label: 'تسليم',
        doneLabel: 'تم التسليم',
        icon: FolderCheck,
        buttonClass: 'bg-green-600 hover:bg-green-700 text-white',
        doneClass: 'text-green-700',
    },
    {
        key: 'completed',
        label: 'اكتمال',
        doneLabel: 'مكتمل',
        icon: CheckCircle2,
        buttonClass: 'bg-emerald-700 hover:bg-emerald-800 text-white',
        doneClass: 'text-emerald-700',
    },
];

const steps = computed(() => (
    isCurrentStudent.value ? currentStudentSteps : admissionSteps
));

const isClosed = computed(() => ['cancelled', 'absent'].includes(props.ticket.status));
const isBusyTicket = computed(() => props.busyId === props.ticket.id);

function isDone(step) {
    const ticket = props.ticket;

    if (step.key === 'entered') {
        return Boolean(ticket.has_entered);
    }
    if (step.key === 'documents_reviewed') {
        return Boolean(ticket.has_documents_reviewed);
    }
    if (step.key === 'medical_checked') {
        return Boolean(ticket.has_medical_checked);
    }
    if (step.key === 'face_printed') {
        return Boolean(ticket.has_face_printed);
    }
    if (step.key === 'file_delivered') {
        return Boolean(ticket.file_delivered);
    }

    return ticket.status === 'completed';
}

function canMark(step) {
    if (isClosed.value || props.ticket.status === 'completed') {
        return false;
    }

    if (step.requiresSystem && !props.systemOpen) {
        return false;
    }

    if (step.key === 'entered') {
        return ['waiting', 'serving'].includes(props.ticket.status) && !props.ticket.has_entered;
    }
    if (step.key === 'documents_reviewed') {
        return Boolean(props.ticket.has_entered) && !props.ticket.has_documents_reviewed;
    }
    if (step.key === 'medical_checked') {
        return Boolean(props.ticket.has_entered) && !props.ticket.has_medical_checked;
    }
    if (step.key === 'face_printed') {
        return Boolean(props.ticket.has_medical_checked) && !props.ticket.has_face_printed;
    }
    if (step.key === 'file_delivered') {
        const previousDone = isCurrentStudent.value
            ? Boolean(props.ticket.has_documents_reviewed)
            : Boolean(props.ticket.has_face_printed);

        return previousDone && !props.ticket.file_delivered;
    }

    return Boolean(props.ticket.file_delivered);
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
        <template v-for="step in steps" :key="step.key">
            <button
                v-if="canMark(step)"
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
                v-else-if="isDone(step)"
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
