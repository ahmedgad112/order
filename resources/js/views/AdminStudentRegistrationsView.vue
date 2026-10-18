<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { Download, RefreshCw, RotateCcw, Search, Trash2 } from 'lucide-vue-next';
import { axios } from '../bootstrap';
import AppNavbar from '../components/AppNavbar.vue';

const registrations = ref([]);
const departments = ref([]);
const sections = ref([]);
const total = ref(0);
const currentPage = ref(1);
const lastPage = ref(1);
const department = ref('');
const sectionNumber = ref('');
const search = ref('');
const loading = ref(false);
const downloading = ref(false);
const resetting = ref(false);
const deletingId = ref(null);
const completingId = ref(null);
const absentId = ref(null);
const notice = ref('');
const error = ref('');

const orderedRegistrations = computed(() => [...registrations.value].sort((left, right) => {
    const byTurn = Number(left.turn_number) - Number(right.turn_number);

    return byTurn === 0 ? Number(left.id) - Number(right.id) : byTurn;
}));

function peopleAheadLabel(count) {
    return Number(count) === 0 ? 'مفيش حد قدامه' : `قدامه ${count} نفر`;
}

function formatDate(value) {
    if (!value) {
        return '—';
    }

    return new Date(value).toLocaleString('ar-EG');
}

function currentFilters() {
    const params = {};

    if (department.value) {
        params.department = department.value;
    }

    if (sectionNumber.value) {
        params.section_number = sectionNumber.value;
    }

    if (search.value.trim()) {
        params.search = search.value.trim();
    }

    return params;
}

async function load(page = currentPage.value) {
    loading.value = true;
    error.value = '';

    try {
        const { data } = await axios.get('/admin/student-registrations', {
            params: {
                ...currentFilters(),
                page,
            },
        });

        registrations.value = data.registrations ?? [];
        departments.value = data.departments ?? [];
        sections.value = data.sections ?? [];
        total.value = data.total ?? 0;
        currentPage.value = data.current_page ?? 1;
        lastPage.value = data.last_page ?? 1;
    } catch {
        error.value = 'تعذر تحميل سجل الطلاب.';
    } finally {
        loading.value = false;
    }
}

async function download(filters, filename) {
    downloading.value = true;
    error.value = '';

    try {
        const response = await axios.get('/admin/student-registrations/export', {
            params: filters,
            responseType: 'blob',
        });
        const url = URL.createObjectURL(response.data);
        const link = document.createElement('a');
        link.href = url;
        link.download = filename;
        document.body.appendChild(link);
        link.click();
        link.remove();
        URL.revokeObjectURL(url);
    } catch {
        error.value = 'تعذر تنزيل الجدول.';
    } finally {
        downloading.value = false;
    }
}

function downloadCurrent() {
    const label = department.value
        ? `${department.value}${sectionNumber.value ? `-سكشن-${sectionNumber.value}` : ''}`
        : 'كل-الأقسام';

    return download(currentFilters(), `سجل-الطلاب-${label}.xml`);
}

async function resetDay() {
    if (!confirm('هيتصفر كل تسجيلات الطلاب ويبدأ الدور من 1، والطلاب يقدروا يسجلوا تاني. متأكد؟')) {
        return;
    }

    resetting.value = true;
    error.value = '';
    notice.value = '';

    try {
        const { data } = await axios.post('/admin/student-registrations/reset-day');
        notice.value = data.message ?? 'تم تصفير أدوار اليوم.';
        await load(1);
    } catch {
        error.value = 'تعذر تصفير الأدوار.';
    } finally {
        resetting.value = false;
    }
}

async function completeRegistration(registration) {
    completingId.value = registration.id;
    error.value = '';

    try {
        const { data } = await axios.post(`/admin/student-registrations/${registration.id}/complete`);
        const ahead = data.registration?.people_ahead ?? 0;
        notice.value = `تم إنهاء الدور ${registration.turn_number}. ${peopleAheadLabel(ahead)}.`;
        await load(currentPage.value);
    } catch (requestError) {
        notice.value = '';
        error.value = requestError.response?.data?.errors?.registration?.[0]
            ?? 'تعذر إنهاء الدور.';
    } finally {
        completingId.value = null;
    }
}

async function markRegistrationAbsent(registration) {
    if (!confirm(`تسجيل الدور ${registration.turn_number} كـ "مش موجود"؟`)) {
        return;
    }

    absentId.value = registration.id;
    error.value = '';

    try {
        const { data } = await axios.post(`/admin/student-registrations/${registration.id}/mark-absent`);
        const ahead = data.registration?.people_ahead ?? 0;
        notice.value = `الدور ${registration.turn_number} مش موجود. ${peopleAheadLabel(ahead)}.`;
        await load(currentPage.value);
    } catch (requestError) {
        notice.value = '';
        error.value = requestError.response?.data?.errors?.registration?.[0]
            ?? 'تعذر تسجيل "مش موجود".';
    } finally {
        absentId.value = null;
    }
}

async function removeRegistration(registration) {
    if (!confirm(`هل تريد حذف تسجيل "${registration.full_name}"؟ يمكنه التسجيل مرة أخرى بعد الحذف.`)) {
        return;
    }

    deletingId.value = registration.id;
    error.value = '';

    try {
        await axios.delete(`/admin/student-registrations/${registration.id}`);
        const nextPage = registrations.value.length === 1 && currentPage.value > 1
            ? currentPage.value - 1
            : currentPage.value;
        await load(nextPage);
    } catch {
        error.value = 'تعذر حذف التسجيل.';
    } finally {
        deletingId.value = null;
    }
}

function submitSearch() {
    currentPage.value = 1;
    load(1);
}

watch(department, () => {
    if (sectionNumber.value !== '') {
        sectionNumber.value = '';

        return;
    }

    currentPage.value = 1;
    load(1);
});

watch(sectionNumber, () => {
    currentPage.value = 1;
    load(1);
});

onMounted(() => {
    load(1);
});
</script>

<template>
    <AppNavbar title="سجل الطلاب" subtitle="الأدوار مرتبة حتى لو اختلف السكشن">
        <main class="mx-auto flex w-full max-w-7xl flex-col gap-4 px-3 py-4 sm:px-6 sm:py-6">
            <section class="rounded-3xl bg-white p-4 shadow-sm sm:p-5">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <h2 class="text-xl font-bold text-slate-900">الطلاب المسجلون</h2>
                        <p class="mt-1 text-sm text-slate-500">{{ total }} طالب. الأرقام ظاهرة بترتيب الدور، حتى لو الطلاب من سكاشن مختلفة.</p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <button
                            type="button"
                            class="inline-flex items-center justify-center gap-2 rounded-2xl border border-amber-300 bg-amber-50 px-4 py-3 text-sm font-bold text-amber-900 hover:bg-amber-100 disabled:opacity-60"
                            :disabled="resetting || loading || total === 0"
                            @click="resetDay"
                        >
                            <RotateCcw class="h-4 w-4" />
                            {{ resetting ? 'جاري التصفير...' : 'تصفير أدوار اليوم' }}
                        </button>
                        <button
                            type="button"
                            class="inline-flex items-center justify-center gap-2 rounded-2xl bg-indigo-600 px-4 py-3 text-sm font-bold text-white hover:bg-indigo-700 disabled:opacity-60"
                            :disabled="downloading || total === 0"
                            @click="downloadCurrent"
                        >
                            <Download class="h-4 w-4" />
                            تنزيل الجداول
                        </button>
                    </div>
                </div>

                <form class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-4" @submit.prevent="submitSearch">
                    <label class="flex flex-col gap-1 text-sm font-semibold text-slate-700">
                        القسم
                        <select
                            v-model="department"
                            class="rounded-xl border border-slate-200 bg-white px-3 py-2.5 font-normal outline-none focus:border-indigo-500"
                        >
                            <option value="">كل الأقسام</option>
                            <option v-for="item in departments" :key="item" :value="item">{{ item }}</option>
                        </select>
                    </label>
                    <label class="flex flex-col gap-1 text-sm font-semibold text-slate-700">
                        السكشن
                        <select
                            v-model="sectionNumber"
                            class="rounded-xl border border-slate-200 bg-white px-3 py-2.5 font-normal outline-none focus:border-indigo-500"
                        >
                            <option value="">كل السكاشن</option>
                            <option v-for="item in sections" :key="item" :value="String(item)">سكشن {{ item }}</option>
                        </select>
                    </label>
                    <label class="flex flex-col gap-1 text-sm font-semibold text-slate-700 sm:col-span-2 xl:col-span-1">
                        بحث
                        <input
                            v-model="search"
                            type="search"
                            class="rounded-xl border border-slate-200 px-3 py-2.5 font-normal outline-none focus:border-indigo-500"
                            placeholder="الاسم أو الرقم الأكاديمي أو القومي أو رقم الدور"
                        />
                    </label>
                    <div class="flex items-end gap-2">
                        <button
                            type="submit"
                            class="inline-flex flex-1 items-center justify-center gap-2 rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-bold text-white"
                        >
                            <Search class="h-4 w-4" />
                            بحث
                        </button>
                        <button
                            type="button"
                            class="inline-flex items-center justify-center rounded-xl border border-slate-200 px-3 py-2.5 text-slate-700"
                            aria-label="تحديث"
                            @click="load(currentPage)"
                        >
                            <RefreshCw class="h-4 w-4" :class="{ 'animate-spin': loading }" />
                        </button>
                    </div>
                </form>
            </section>

            <p v-if="notice" class="rounded-2xl bg-emerald-50 px-4 py-3 text-sm font-bold text-emerald-800">{{ notice }}</p>
            <p v-if="error" class="rounded-2xl bg-red-50 px-4 py-3 text-sm font-semibold text-red-700">{{ error }}</p>

            <p v-if="!loading && total === 0" class="rounded-3xl bg-white px-5 py-10 text-center text-slate-500 shadow-sm">
                لا يوجد طلاب مسجلون بهذه البيانات.
            </p>

            <section v-else-if="orderedRegistrations.length" class="rounded-3xl bg-white p-4 shadow-sm sm:p-5">
                <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                    <article
                        v-for="registration in orderedRegistrations"
                        :key="registration.id"
                        class="flex items-start gap-3 rounded-2xl border p-4"
                        :class="{
                            'border-emerald-200 bg-emerald-50/70': registration.is_completed,
                            'border-orange-200 bg-orange-50/70': registration.is_absent,
                            'border-slate-200 bg-slate-50': registration.is_waiting,
                        }"
                    >
                        <div class="flex shrink-0 flex-col gap-2">
                            <template v-if="registration.is_waiting">
                                <button
                                    type="button"
                                    class="rounded-2xl bg-emerald-600 px-4 py-3 text-base font-black text-white hover:bg-emerald-700 disabled:opacity-60"
                                    :disabled="completingId === registration.id || absentId === registration.id"
                                    @click="completeRegistration(registration)"
                                >
                                    {{ completingId === registration.id ? '...' : 'تم' }}
                                </button>
                                <button
                                    type="button"
                                    class="rounded-2xl border border-orange-300 bg-orange-50 px-3 py-2 text-sm font-bold text-orange-800 hover:bg-orange-100 disabled:opacity-60"
                                    :disabled="completingId === registration.id || absentId === registration.id"
                                    @click="markRegistrationAbsent(registration)"
                                >
                                    {{ absentId === registration.id ? '...' : 'مش موجود' }}
                                </button>
                            </template>
                            <span
                                v-else-if="registration.is_completed"
                                class="rounded-2xl bg-emerald-600 px-4 py-3 text-base font-black text-white"
                            >
                                تم
                            </span>
                            <span
                                v-else
                                class="rounded-2xl bg-orange-500 px-3 py-3 text-sm font-black text-white"
                            >
                                مش موجود
                            </span>
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="text-3xl font-black text-indigo-700">{{ registration.turn_number }}</p>
                            <p class="mt-1 font-bold text-slate-900">{{ registration.full_name }}</p>
                            <p
                                v-if="!registration.is_waiting && registration.people_ahead !== null"
                                class="mt-2 rounded-xl bg-white px-3 py-2 text-sm font-bold text-amber-800"
                            >
                                {{ peopleAheadLabel(registration.people_ahead) }}
                            </p>
                            <dl class="mt-3 grid gap-1 text-sm text-slate-600">
                                <div class="flex justify-between gap-3">
                                    <dt>الرقم الأكاديمي</dt>
                                    <dd class="font-semibold tracking-wider">{{ registration.academic_number }}</dd>
                                </div>
                                <div class="flex justify-between gap-3">
                                    <dt>الرقم القومي</dt>
                                    <dd class="font-semibold tracking-wider">{{ registration.national_id }}</dd>
                                </div>
                                <div class="flex justify-between gap-3">
                                    <dt>القسم / السكشن</dt>
                                    <dd class="font-semibold">{{ registration.department }} — {{ registration.section_number }}</dd>
                                </div>
                                <div class="flex justify-between gap-3">
                                    <dt>المجموعة</dt>
                                    <dd class="font-bold text-slate-900">{{ registration.group_code }}</dd>
                                </div>
                                <div class="flex justify-between gap-3">
                                    <dt>التسجيل</dt>
                                    <dd>{{ formatDate(registration.created_at) }}</dd>
                                </div>
                            </dl>
                            <button
                                type="button"
                                class="mt-3 inline-flex items-center gap-1 rounded-lg px-2 py-1 text-sm font-semibold text-red-600 hover:bg-red-50 disabled:opacity-50"
                                :disabled="deletingId === registration.id"
                                @click="removeRegistration(registration)"
                            >
                                <Trash2 class="h-4 w-4" />
                                حذف
                            </button>
                        </div>
                    </article>
                </div>
            </section>

            <div v-if="lastPage > 1" class="flex items-center justify-between rounded-2xl bg-white px-4 py-3 text-sm shadow-sm">
                <button
                    type="button"
                    class="rounded-xl border border-slate-200 px-3 py-2 font-semibold disabled:opacity-40"
                    :disabled="currentPage <= 1 || loading"
                    @click="load(currentPage - 1)"
                >
                    السابق
                </button>
                <span class="text-slate-500">صفحة {{ currentPage }} من {{ lastPage }}</span>
                <button
                    type="button"
                    class="rounded-xl border border-slate-200 px-3 py-2 font-semibold disabled:opacity-40"
                    :disabled="currentPage >= lastPage || loading"
                    @click="load(currentPage + 1)"
                >
                    التالي
                </button>
            </div>
        </main>
    </AppNavbar>
</template>
