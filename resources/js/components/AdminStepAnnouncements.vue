<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { Megaphone, Volume2, VolumeX } from 'lucide-vue-next';
import { axios } from '../bootstrap';
import { useAuthStore } from '../stores/authStore';
import { useQueueStore } from '../stores/queueStore';

const authStore = useAuthStore();
const queueStore = useQueueStore();

const canManage = computed(() => authStore.canControlSystem);

const steps = ref([]);
const callTemplate = ref('');
const savingSteps = ref(false);
const savingTemplate = ref(false);
const stepsFeedback = ref('');
const templateFeedback = ref('');
const stepsError = ref('');
const templateError = ref('');

const DEFAULT_TEMPLATE = 'رَقَم {order}، {name}، بُرْجَاء التَّوَجُّه إِلَى {counter}';

let templateLoaded = false;

watch(() => queueStore.system.call_template, (value) => {
    if (value && !templateLoaded) {
        callTemplate.value = value;
        templateLoaded = true;
    }
}, { immediate: true });

onMounted(() => {
    loadSteps();
});

async function loadSteps() {
    if (!canManage.value) {
        return;
    }

    try {
        const { data } = await axios.get('/admin/step-announcements');
        steps.value = data.steps ?? [];
    } catch {
        steps.value = [];
    }
}

function firstError(err, fallback) {
    const errors = err.response?.data?.errors ?? {};
    const firstKey = Object.keys(errors)[0];

    return errors[firstKey]?.[0] ?? err.response?.data?.message ?? fallback;
}

async function saveSteps() {
    savingSteps.value = true;
    stepsError.value = '';
    stepsFeedback.value = '';

    try {
        const { data } = await axios.put('/admin/step-announcements', {
            steps: steps.value.map((item) => ({
                step: item.step,
                enabled: Boolean(item.enabled),
                destination: item.destination?.trim() || null,
            })),
        });
        steps.value = data.steps ?? steps.value;
        stepsFeedback.value = data.message ?? 'تم حفظ إعدادات النداء.';
    } catch (err) {
        stepsError.value = firstError(err, 'تعذر حفظ إعدادات النداء.');
    } finally {
        savingSteps.value = false;
    }
}

async function saveTemplate() {
    savingTemplate.value = true;
    templateError.value = '';
    templateFeedback.value = '';

    try {
        const { data } = await axios.put('/admin/system/call-template', {
            call_template: callTemplate.value.trim() || null,
        });
        callTemplate.value = data.system?.call_template ?? callTemplate.value;
        templateFeedback.value = data.message ?? 'تم حفظ نص النداء.';
    } catch (err) {
        templateError.value = firstError(err, 'تعذر حفظ نص النداء.');
    } finally {
        savingTemplate.value = false;
    }
}

function resetTemplate() {
    callTemplate.value = DEFAULT_TEMPLATE;
    templateFeedback.value = '';
    templateError.value = '';
}
</script>

<template>
    <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
        <div class="mb-4 flex items-center gap-2">
            <Megaphone class="h-5 w-5 text-indigo-600" />
            <h2 class="text-lg font-bold text-slate-900">إعدادات النداء الصوتي</h2>
        </div>

        <p v-if="!canManage" class="text-sm font-semibold text-slate-500">
            إعدادات النداء متاحة للسوبر أدمن فقط.
        </p>

        <template v-else>
            <div class="mb-6 rounded-2xl border border-slate-200 bg-slate-50 p-4">
                <label class="mb-1 block text-sm font-semibold text-slate-700">نص النداء</label>
                <textarea
                    v-model="callTemplate"
                    rows="2"
                    maxlength="500"
                    class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm outline-none focus:border-indigo-400"
                />
                <p class="mt-2 text-xs leading-5 text-slate-500">
                    المتغيرات المتاحة:
                    <code class="rounded bg-slate-200 px-1 font-mono" dir="ltr">{order}</code> رقم الدور،
                    <code class="rounded bg-slate-200 px-1 font-mono" dir="ltr">{name}</code> اسم الطالب،
                    <code class="rounded bg-slate-200 px-1 font-mono" dir="ltr">{counter}</code> اسم شباك الموظف أو نوع الطلب،
                    <code class="rounded bg-slate-200 px-1 font-mono" dir="ltr">{destination}</code> وجهة الخطوة،
                    <code class="rounded bg-slate-200 px-1 font-mono" dir="ltr">{type}</code> نوع الطلب.
                    اترك الحقل فارغاً للعودة إلى النص الافتراضي.
                </p>
                <p v-if="templateError" class="mt-2 rounded-xl bg-red-50 px-3 py-2 text-xs text-red-700">
                    {{ templateError }}
                </p>
                <p v-if="templateFeedback" class="mt-2 rounded-xl bg-emerald-50 px-3 py-2 text-xs font-semibold text-emerald-700">
                    {{ templateFeedback }}
                </p>
                <div class="mt-3 flex gap-2">
                    <button
                        type="button"
                        class="rounded-xl bg-indigo-600 px-4 py-2 text-sm font-bold text-white hover:bg-indigo-700 disabled:opacity-60"
                        :disabled="savingTemplate"
                        @click="saveTemplate"
                    >
                        {{ savingTemplate ? 'جاري الحفظ...' : 'حفظ نص النداء' }}
                    </button>
                    <button
                        type="button"
                        class="rounded-xl border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-white"
                        @click="resetTemplate"
                    >
                        استعادة الافتراضي
                    </button>
                </div>
            </div>

            <h3 class="mb-2 text-sm font-bold text-slate-700">نداء خطوات المعاملة</h3>
            <p class="mb-3 text-xs text-slate-500">
                النداء يقول اسم شباك الموظف أو نوع الطلب دائماً. الوجهة هنا اختيارية وتُنطق فقط إذا أضفت
                <code class="rounded bg-slate-200 px-1 font-mono" dir="ltr">{destination}</code>
                إلى نص النداء.
            </p>

            <ul class="space-y-2">
                <li
                    v-for="item in steps"
                    :key="item.step"
                    class="flex flex-wrap items-center gap-3 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2"
                >
                    <span class="min-w-[7rem] text-sm font-semibold text-slate-800">{{ item.label }}</span>
                    <label class="flex items-center gap-1.5 text-xs font-semibold text-slate-600">
                        <input
                            v-model="item.enabled"
                            type="checkbox"
                            class="h-4 w-4 accent-indigo-600"
                        />
                        <component :is="item.enabled ? Volume2 : VolumeX" class="h-4 w-4 text-slate-500" />
                        صوت
                    </label>
                    <input
                        v-model="item.destination"
                        type="text"
                        maxlength="120"
                        class="min-w-0 flex-1 rounded-xl border border-slate-200 bg-white px-3 py-1.5 text-sm outline-none focus:border-indigo-400"
                        placeholder="اختياري: وجهة الخطوة"
                    />
                </li>
            </ul>

            <p v-if="stepsError" class="mt-3 rounded-xl bg-red-50 px-3 py-2 text-xs text-red-700">
                {{ stepsError }}
            </p>
            <p v-if="stepsFeedback" class="mt-3 rounded-xl bg-emerald-50 px-3 py-2 text-xs font-semibold text-emerald-700">
                {{ stepsFeedback }}
            </p>

            <button
                type="button"
                class="mt-4 rounded-xl bg-indigo-600 px-4 py-2 text-sm font-bold text-white hover:bg-indigo-700 disabled:opacity-60"
                :disabled="savingSteps"
                @click="saveSteps"
            >
                {{ savingSteps ? 'جاري الحفظ...' : 'حفظ إعدادات الخطوات' }}
            </button>
        </template>
    </section>
</template>
