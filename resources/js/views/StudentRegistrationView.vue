<script setup>
import { computed, ref } from 'vue';
import { RouterLink } from 'vue-router';
import { ArrowRight, Building2, CheckCircle2, CreditCard, Hash, Layers, Search, User } from 'lucide-vue-next';
import { axios } from '../bootstrap';
import AppNavbar from '../components/AppNavbar.vue';

const digitMap = {
    '٠': '0', '١': '1', '٢': '2', '٣': '3', '٤': '4',
    '٥': '5', '٦': '6', '٧': '7', '٨': '8', '٩': '9',
    '۰': '0', '۱': '1', '۲': '2', '۳': '3', '۴': '4',
    '۵': '5', '۶': '6', '۷': '7', '۸': '8', '۹': '9',
};

const form = ref(emptyForm());
const fieldErrors = ref({});
const submitting = ref(false);
const submitted = ref(null);

function emptyForm() {
    return {
        full_name: '',
        academic_number: '',
        national_id: '',
        department: '',
        section_number: '',
        group_number: '',
        student_group: '',
    };
}

const lookupQuery = ref('');
const lookupResult = ref(null);
const lookupError = ref('');
const lookingUp = ref(false);
const groupChoiceReady = computed(() => /^\d+$/.test(form.value.group_number) && Number(form.value.group_number) >= 1);

function digitsOnly(value, maxLength) {
    const normalized = String(value ?? '').replace(/[٠-٩۰-۹]/g, (char) => digitMap[char] ?? '');

    return normalized.replace(/\D/g, '').slice(0, maxLength);
}

function onAcademicNumberInput(event) {
    form.value.academic_number = digitsOnly(event.target.value, 20);
    event.target.value = form.value.academic_number;
}

function onSectionInput(event) {
    form.value.section_number = digitsOnly(event.target.value, 3);
    event.target.value = form.value.section_number;
}

function onGroupNumberInput(event) {
    form.value.group_number = digitsOnly(event.target.value, 3);
    event.target.value = form.value.group_number;

    if (!groupChoiceReady.value) {
        form.value.student_group = '';
    }
}

function onLookupInput(event) {
    lookupQuery.value = digitsOnly(event.target.value, 20);
    event.target.value = lookupQuery.value;
}

function onNationalIdInput(event) {
    form.value.national_id = digitsOnly(event.target.value, 14);
    event.target.value = form.value.national_id;
}

function validateClient() {
    const errors = {};
    const name = form.value.full_name.trim().replace(/\s+/g, ' ');
    const department = form.value.department.trim().replace(/\s+/g, ' ');
    const section = Number(form.value.section_number);

    if (name.length < 3) {
        errors.full_name = 'يجب أن يتكون الاسم من 3 أحرف على الأقل.';
    }

    if (!/^\d{4,20}$/.test(form.value.academic_number)) {
        errors.academic_number = 'يجب أن يتكون الرقم الأكاديمي من 4 إلى 20 رقمًا.';
    }

    if (!/^\d{14}$/.test(form.value.national_id)) {
        errors.national_id = 'يجب أن يتكون الرقم القومي من 14 رقمًا بالضبط.';
    }

    if (department.length < 2) {
        errors.department = 'يجب أن يتكون اسم القسم من حرفين على الأقل.';
    }

    if (!Number.isInteger(section) || section < 1 || section > 999) {
        errors.section_number = 'رقم السكشن يجب أن يكون من 1 إلى 999.';
    }

    const groupNumber = Number(form.value.group_number);

    if (!Number.isInteger(groupNumber) || groupNumber < 1 || groupNumber > 999) {
        errors.group_number = 'رقم المجموعة يجب أن يكون من 1 إلى 999.';
    } else if (!['A', 'B'].includes(form.value.student_group)) {
        errors.student_group = 'يجب اختيار A أو B.';
    }

    fieldErrors.value = errors;

    return Object.keys(errors).length === 0;
}

function applyServerErrors(error) {
    const errors = error?.response?.data?.errors ?? {};
    const next = {};

    for (const [field, messages] of Object.entries(errors)) {
        next[field] = Array.isArray(messages) ? messages[0] : messages;
    }

    if (!Object.keys(next).length) {
        next.general = 'تعذر حفظ البيانات. حاول مرة أخرى.';
    }

    fieldErrors.value = next;
}

async function submitForm() {
    if (!validateClient() || submitting.value) {
        return;
    }

    submitting.value = true;
    fieldErrors.value = {};

    const payload = {
        full_name: form.value.full_name.trim().replace(/\s+/g, ' '),
        academic_number: form.value.academic_number,
        national_id: form.value.national_id,
        department: form.value.department.trim().replace(/\s+/g, ' '),
        section_number: Number(form.value.section_number),
        group_number: Number(form.value.group_number),
        student_group: form.value.student_group,
    };

    try {
        const { data } = await axios.post('/public/student-registrations', payload);
        submitted.value = data.registration;
        form.value = emptyForm();
    } catch (error) {
        applyServerErrors(error);
    } finally {
        submitting.value = false;
    }
}

function registerAnother() {
    submitted.value = null;
    fieldErrors.value = {};
}

async function lookupRegistration() {
    const query = lookupQuery.value.trim();

    if (!/^\d{4,20}$/.test(query)) {
        lookupError.value = 'اكتب الرقم الأكاديمي أو الرقم القومي بشكل صحيح.';
        lookupResult.value = null;

        return;
    }

    lookingUp.value = true;
    lookupError.value = '';

    try {
        const { data } = await axios.post('/public/student-registrations/lookup', { query });
        lookupResult.value = data.registration;
    } catch (error) {
        lookupResult.value = null;
        lookupError.value = error?.response?.data?.errors?.query?.[0]
            ?? 'تعذر البحث. حاول مرة أخرى.';
    } finally {
        lookingUp.value = false;
    }
}

const inputClass = 'w-full rounded-2xl border border-slate-200 px-5 py-4 text-lg outline-none transition focus:border-indigo-500 focus:ring-4 focus:ring-indigo-100';
</script>

<template>
    <AppNavbar
        title="نظام إدارة الأدوار"
        subtitle="تسجيل بيانات الطالب"
        page-class="bg-gradient-to-br from-blue-50 via-white to-indigo-50"
    >
        <main class="mx-auto flex w-full max-w-3xl flex-col gap-6 px-4 py-6 sm:px-6 sm:py-10">
            <section id="lookup" class="rounded-3xl border border-slate-200 bg-white p-6 shadow-xl sm:p-8">
                <h2 class="text-center text-2xl font-bold text-slate-800">ابحث عن دورك</h2>
                <p class="mt-2 text-center text-sm text-slate-500">اكتب الرقم الأكاديمي أو الرقم القومي عشان يظهر رقم الدور.</p>
                <form class="mt-6 flex flex-col gap-3 sm:flex-row" @submit.prevent="lookupRegistration">
                    <input
                        :value="lookupQuery"
                        type="text"
                        inputmode="numeric"
                        autocomplete="off"
                        maxlength="20"
                        class="w-full rounded-2xl border border-slate-200 px-5 py-4 text-lg tracking-widest outline-none transition focus:border-indigo-500 focus:ring-4 focus:ring-indigo-100"
                        placeholder="الرقم الأكاديمي أو القومي"
                        @input="onLookupInput"
                    />
                    <button
                        type="submit"
                        :disabled="lookingUp"
                        class="inline-flex shrink-0 items-center justify-center gap-2 rounded-2xl bg-slate-900 px-6 py-4 text-lg font-bold text-white hover:bg-slate-800 disabled:opacity-60"
                    >
                        <Search class="h-5 w-5" />
                        {{ lookingUp ? 'جاري البحث...' : 'بحث' }}
                    </button>
                </form>
                <p v-if="lookupError" class="mt-4 rounded-xl bg-red-50 px-4 py-3 text-center text-red-700">{{ lookupError }}</p>
                <div v-if="lookupResult" class="mt-6 rounded-2xl bg-indigo-50 px-5 py-6 text-center">
                    <p class="text-sm font-semibold text-slate-500">دور {{ lookupResult.full_name }}</p>
                    <p class="text-6xl font-black text-indigo-700">{{ lookupResult.turn_number }}</p>
                    <p class="mt-2 font-bold text-slate-800">المجموعة {{ lookupResult.group_code }}</p>
                    <p class="mt-1 text-sm text-slate-600">{{ lookupResult.department }} — سكشن {{ lookupResult.section_number }} — {{ lookupResult.status_label }}</p>
                </div>
            </section>

            <RouterLink
                to="/"
                class="inline-flex items-center gap-2 text-sm font-semibold text-slate-600 hover:text-indigo-700"
            >
                <ArrowRight class="h-4 w-4" />
                الرجوع للرئيسية
            </RouterLink>

            <section
                v-if="submitted"
                class="rounded-3xl border border-emerald-200 bg-white p-6 text-center shadow-xl sm:p-8"
            >
                <CheckCircle2 class="mx-auto h-12 w-12 text-emerald-600" />
                <h2 class="mt-4 text-2xl font-bold text-slate-900">تم تسجيل بياناتك</h2>
                <p class="mt-4 text-sm font-semibold text-slate-500">دورك</p>
                <p class="text-6xl font-black text-indigo-700">{{ submitted.turn_number }}</p>
                <p class="mt-2 text-slate-500">احتفظ برقم الدور. موظف الشباك هيؤشر تم لما تيجي.</p>
                <dl class="mt-6 grid gap-3 text-start">
                    <div class="rounded-2xl bg-slate-50 px-4 py-3">
                        <dt class="text-xs font-semibold text-slate-500">الاسم</dt>
                        <dd class="text-lg font-bold text-slate-900">{{ submitted.full_name }}</dd>
                    </div>
                    <div class="rounded-2xl bg-slate-50 px-4 py-3">
                        <dt class="text-xs font-semibold text-slate-500">الرقم الأكاديمي</dt>
                        <dd class="text-lg font-bold tracking-wider text-slate-900">{{ submitted.academic_number }}</dd>
                    </div>
                    <div class="rounded-2xl bg-slate-50 px-4 py-3">
                        <dt class="text-xs font-semibold text-slate-500">الرقم القومي</dt>
                        <dd class="text-lg font-bold tracking-wider text-slate-900">{{ submitted.national_id }}</dd>
                    </div>
                    <div class="grid gap-3 sm:grid-cols-3">
                        <div class="rounded-2xl bg-slate-50 px-4 py-3">
                            <dt class="text-xs font-semibold text-slate-500">القسم</dt>
                            <dd class="font-bold text-slate-900">{{ submitted.department }}</dd>
                        </div>
                        <div class="rounded-2xl bg-slate-50 px-4 py-3">
                            <dt class="text-xs font-semibold text-slate-500">السكشن</dt>
                            <dd class="font-bold text-slate-900">{{ submitted.section_number }}</dd>
                        </div>
                        <div class="rounded-2xl bg-slate-50 px-4 py-3">
                            <dt class="text-xs font-semibold text-slate-500">المجموعة</dt>
                            <dd class="font-bold text-slate-900">{{ submitted.group_code }}</dd>
                        </div>
                    </div>
                </dl>
                <button
                    type="button"
                    class="mt-6 w-full rounded-2xl bg-indigo-600 px-6 py-4 text-lg font-bold text-white hover:bg-indigo-700"
                    @click="registerAnother"
                >
                    تسجيل طالب آخر
                </button>
            </section>

            <section
                v-else
                class="rounded-3xl border border-slate-200 bg-white p-6 shadow-xl sm:p-8"
            >
                <h2 class="text-center text-2xl font-bold text-slate-800 sm:text-3xl">سجّل بياناتك</h2>
                <p class="mt-2 text-center text-sm text-slate-500">اكتب رقم المجموعة، وبعدين اختار A أو B. هتاخد رقم دور بعد الحفظ.</p>

                <p v-if="fieldErrors.general" class="mt-6 rounded-xl bg-red-50 px-4 py-3 text-center text-red-700">
                    {{ fieldErrors.general }}
                </p>

                <form class="mt-8 flex flex-col gap-6" @submit.prevent="submitForm">
                    <div>
                        <label class="mb-2 flex items-center gap-2 text-sm font-semibold text-slate-700" for="student-name">
                            <User class="h-4 w-4" />
                            اسم الطالب
                        </label>
                        <input
                            id="student-name"
                            v-model="form.full_name"
                            type="text"
                            autocomplete="name"
                            :class="inputClass"
                            placeholder="مثال: أحمد محمود علي"
                        />
                        <p v-if="fieldErrors.full_name" class="mt-2 text-sm text-red-600">{{ fieldErrors.full_name }}</p>
                    </div>

                    <div>
                        <label class="mb-2 flex items-center gap-2 text-sm font-semibold text-slate-700" for="academic-number">
                            <Hash class="h-4 w-4" />
                            الرقم الأكاديمي
                        </label>
                        <input
                            id="academic-number"
                            :value="form.academic_number"
                            type="text"
                            inputmode="numeric"
                            autocomplete="off"
                            maxlength="20"
                            :class="[inputClass, 'tracking-widest']"
                            placeholder="4 إلى 20 رقمًا"
                            @input="onAcademicNumberInput"
                        />
                        <p v-if="fieldErrors.academic_number" class="mt-2 text-sm text-red-600">{{ fieldErrors.academic_number }}</p>
                    </div>

                    <div>
                        <label class="mb-2 flex items-center gap-2 text-sm font-semibold text-slate-700" for="national-id">
                            <CreditCard class="h-4 w-4" />
                            الرقم القومي
                        </label>
                        <input
                            id="national-id"
                            :value="form.national_id"
                            type="text"
                            inputmode="numeric"
                            autocomplete="off"
                            maxlength="14"
                            :class="[inputClass, 'tracking-widest']"
                            placeholder="14 رقمًا"
                            @input="onNationalIdInput"
                        />
                        <p v-if="fieldErrors.national_id" class="mt-2 text-sm text-red-600">{{ fieldErrors.national_id }}</p>
                    </div>

                    <div>
                        <label class="mb-2 flex items-center gap-2 text-sm font-semibold text-slate-700" for="department">
                            <Building2 class="h-4 w-4" />
                            القسم
                        </label>
                        <input
                            id="department"
                            v-model="form.department"
                            type="text"
                            :class="inputClass"
                            placeholder="اكتب اسم القسم"
                        />
                        <p v-if="fieldErrors.department" class="mt-2 text-sm text-red-600">{{ fieldErrors.department }}</p>
                    </div>

                    <div>
                        <label class="mb-2 flex items-center gap-2 text-sm font-semibold text-slate-700" for="section-number">
                            <Layers class="h-4 w-4" />
                            رقم السكشن
                        </label>
                        <input
                            id="section-number"
                            :value="form.section_number"
                            type="text"
                            inputmode="numeric"
                            autocomplete="off"
                            maxlength="3"
                            :class="inputClass"
                            placeholder="مثال: 1"
                            @input="onSectionInput"
                        />
                        <p v-if="fieldErrors.section_number" class="mt-2 text-sm text-red-600">{{ fieldErrors.section_number }}</p>
                    </div>

                    <div>
                        <label class="mb-2 flex items-center gap-2 text-sm font-semibold text-slate-700" for="group-number">
                            <Hash class="h-4 w-4" />
                            رقم المجموعة
                        </label>
                        <input
                            id="group-number"
                            :value="form.group_number"
                            type="text"
                            inputmode="numeric"
                            autocomplete="off"
                            maxlength="3"
                            :class="inputClass"
                            placeholder="مثال: 4"
                            @input="onGroupNumberInput"
                        />
                        <p v-if="fieldErrors.group_number" class="mt-2 text-sm text-red-600">{{ fieldErrors.group_number }}</p>
                    </div>

                    <fieldset v-if="groupChoiceReady">
                        <legend class="mb-2 text-sm font-semibold text-slate-700">بعد الرقم، اختار A أو B</legend>
                        <div class="grid grid-cols-2 gap-3">
                            <label
                                v-for="group in ['A', 'B']"
                                :key="group"
                                class="flex cursor-pointer items-center justify-center gap-2 rounded-2xl border px-5 py-4 text-lg font-bold transition"
                                :class="form.student_group === group
                                    ? 'border-indigo-500 bg-indigo-50 text-indigo-800 ring-4 ring-indigo-100'
                                    : 'border-slate-200 text-slate-700 hover:border-indigo-300'"
                            >
                                <input
                                    v-model="form.student_group"
                                    type="radio"
                                    class="h-4 w-4 accent-indigo-600"
                                    :value="group"
                                />
                                {{ group }}
                            </label>
                        </div>
                        <p v-if="fieldErrors.student_group" class="mt-2 text-sm text-red-600">{{ fieldErrors.student_group }}</p>
                    </fieldset>

                    <button
                        type="submit"
                        :disabled="submitting"
                        class="flex w-full items-center justify-center rounded-2xl bg-indigo-600 px-6 py-5 text-xl font-bold text-white transition hover:bg-indigo-700 disabled:cursor-not-allowed disabled:opacity-60"
                    >
                        {{ submitting ? 'جاري الحفظ...' : 'حفظ البيانات' }}
                    </button>
                </form>
            </section>
        </main>
    </AppNavbar>
</template>
