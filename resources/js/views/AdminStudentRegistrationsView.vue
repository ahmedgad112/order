<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { Download, RefreshCw, Search, Trash2 } from 'lucide-vue-next';
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
const deletingId = ref(null);
const error = ref('');

const groups = computed(() => {
    const grouped = new Map();

    for (const registration of registrations.value) {
        const key = `${registration.department}::${registration.section_number}`;

        if (!grouped.has(key)) {
            grouped.set(key, {
                department: registration.department,
                section_number: registration.section_number,
                rows: [],
            });
        }

        grouped.get(key).rows.push(registration);
    }

    return [...grouped.values()];
});

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

    return download(currentFilters(), `سجل-الطلاب-${label}.xls`);
}

function downloadGroup(group) {
    return download(
        {
            department: group.department,
            section_number: group.section_number,
        },
        `سجل-الطلاب-${group.department}-سكشن-${group.section_number}.xls`,
    );
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
    <AppNavbar title="سجل الطلاب" subtitle="جداول التسجيل حسب القسم والسكشن">
        <main class="mx-auto flex w-full max-w-7xl flex-col gap-4 px-3 py-4 sm:px-6 sm:py-6">
            <section class="rounded-3xl bg-white p-4 shadow-sm sm:p-5">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <h2 class="text-xl font-bold text-slate-900">الطلاب المسجلون</h2>
                        <p class="mt-1 text-sm text-slate-500">{{ total }} طالب. كل سكشن في جدول مستقل، ويمكن تنزيل الجداول لفتحها في إكسل.</p>
                    </div>
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
                            placeholder="الاسم أو الرقم الأكاديمي أو القومي"
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

            <p v-if="error" class="rounded-2xl bg-red-50 px-4 py-3 text-sm font-semibold text-red-700">{{ error }}</p>

            <p v-if="!loading && total === 0" class="rounded-3xl bg-white px-5 py-10 text-center text-slate-500 shadow-sm">
                لا يوجد طلاب مسجلون بهذه البيانات.
            </p>

            <section
                v-for="group in groups"
                :key="`${group.department}-${group.section_number}`"
                class="overflow-hidden rounded-3xl bg-white shadow-sm"
            >
                <div class="flex flex-col gap-3 border-b border-slate-100 px-4 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-5">
                    <div>
                        <h3 class="text-lg font-bold text-slate-900">{{ group.department }}</h3>
                        <p class="text-sm text-slate-500">سكشن {{ group.section_number }}</p>
                    </div>
                    <button
                        type="button"
                        class="inline-flex items-center justify-center gap-2 rounded-xl border border-indigo-200 bg-indigo-50 px-3 py-2 text-sm font-bold text-indigo-700 hover:bg-indigo-100 disabled:opacity-60"
                        :disabled="downloading"
                        @click="downloadGroup(group)"
                    >
                        <Download class="h-4 w-4" />
                        تنزيل جدول السكشن
                    </button>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-slate-50 text-slate-500">
                            <tr>
                                <th class="px-4 py-3 text-start font-semibold">الدور</th>
                                <th class="px-4 py-3 text-start font-semibold">الاسم</th>
                                <th class="px-4 py-3 text-start font-semibold">الرقم الأكاديمي</th>
                                <th class="px-4 py-3 text-start font-semibold">الرقم القومي</th>
                                <th class="px-4 py-3 text-start font-semibold">القسم</th>
                                <th class="px-4 py-3 text-start font-semibold">السكشن</th>
                                <th class="px-4 py-3 text-start font-semibold">المجموعة</th>
                                <th class="px-4 py-3 text-start font-semibold">الحالة</th>
                                <th class="px-4 py-3 text-start font-semibold">وقت التسجيل</th>
                                <th class="px-4 py-3 text-start font-semibold">حذف</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="registration in group.rows" :key="registration.id" class="border-t border-slate-100">
                                <td class="px-4 py-3 text-lg font-black text-indigo-700">{{ registration.turn_number }}</td>
                                <td class="px-4 py-3 font-semibold text-slate-900">{{ registration.full_name }}</td>
                                <td class="px-4 py-3 tracking-wider text-slate-700">{{ registration.academic_number }}</td>
                                <td class="px-4 py-3 tracking-wider text-slate-700">{{ registration.national_id }}</td>
                                <td class="px-4 py-3 text-slate-700">{{ registration.department }}</td>
                                <td class="px-4 py-3 text-slate-700">{{ registration.section_number }}</td>
                                <td class="px-4 py-3 font-bold text-slate-900">{{ registration.student_group }}</td>
                                <td class="px-4 py-3">
                                    <span
                                        class="rounded-full px-2.5 py-1 text-xs font-bold"
                                        :class="registration.is_completed ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800'"
                                    >
                                        {{ registration.status_label }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-slate-500">{{ formatDate(registration.created_at) }}</td>
                                <td class="px-4 py-3">
                                    <button
                                        type="button"
                                        class="inline-flex items-center gap-1 rounded-lg px-2 py-1 text-red-600 hover:bg-red-50 disabled:opacity-50"
                                        :disabled="deletingId === registration.id"
                                        @click="removeRegistration(registration)"
                                    >
                                        <Trash2 class="h-4 w-4" />
                                        حذف
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
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
