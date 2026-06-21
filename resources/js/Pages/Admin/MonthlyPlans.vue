<script setup>
import axios from 'axios';
import { Head } from '@inertiajs/vue3';
import Button from 'primevue/button';
import Select from 'primevue/select';
import { computed, onMounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { adminNavItems } from '../../admin/navItems';
import AdminBreadcrumbs from '../../components/admin/AdminBreadcrumbs.vue';
import AdminLayout from '../../components/admin/AdminLayout.vue';
import { useAppToast } from '../../composables/useAppToast';

const props = defineProps({
    centers: {
        type: Array,
        default: () => [],
    },
    groups: {
        type: Array,
        default: () => [],
    },
    default_month: {
        type: Number,
        default: new Date().getMonth() + 1,
    },
    default_year: {
        type: Number,
        default: new Date().getFullYear(),
    },
});

const { t } = useI18n();
const appToast = useAppToast();
const loading = ref(false);
const generating = ref(false);
const plans = ref([]);
const dates = ref([]);
const selectedCenterPayload = ref(null);
const filters = ref({
    center_id: null,
    group_id: null,
    month: props.default_month,
    year: props.default_year,
});

const yearOptions = computed(() => {
    const currentYear = new Date().getFullYear();
    return Array.from({ length: 8 }, (_, index) => currentYear - 2 + index).map((year) => ({
        label: String(year),
        value: year,
    }));
});

const monthOptions = computed(() => Array.from({ length: 12 }, (_, index) => ({
    label: t(`monthlyPlans.months.${index + 1}`),
    value: index + 1,
})));

const groupOptions = computed(() => {
    if (!filters.value.center_id) {
        return props.groups;
    }

    return props.groups.filter((group) => Number(group.center_id) === Number(filters.value.center_id));
});

const selectedGroup = computed(() => groupOptions.value.find((group) => Number(group.id) === Number(filters.value.group_id)) ?? null);
const selectedCenter = computed(() => props.centers.find((center) => Number(center.id) === Number(filters.value.center_id)) ?? null);

const planRows = computed(() => plans.value.map((plan) => ({
    ...plan,
    dayMap: Object.fromEntries((plan.days ?? []).map((day) => [day.date, day])),
})));

const cellItems = (plan, date) => plan.dayMap?.[date]?.items ?? [];
const cellTotalWeight = (plan, date) => plan.dayMap?.[date]?.total_weight ?? 0;

const shortDate = (date) => {
    const [, month, day] = String(date).split('-').map((segment) => Number(segment));

    return month && day ? `${day}/${month}` : date;
};

const fetchPlans = async () => {
    loading.value = true;

    try {
        const { data } = await axios.get('/admin/monthly-plans/records', { params: filters.value });
        const payload = data?.data ?? {};
        plans.value = payload.plans ?? [];
        dates.value = payload.dates ?? [];
        selectedCenterPayload.value = payload.center ?? null;
    } catch (error) {
        appToast.fromAxiosError(error, {
            summary: t('notifications.requestFailedTitle'),
            fallback: t('monthlyPlans.loadFailed'),
        });
    } finally {
        loading.value = false;
    }
};

const generatePlans = async () => {
    if (!filters.value.center_id) {
        appToast.error(t('monthlyPlans.centerRequired'));
        return;
    }

    generating.value = true;

    try {
        const { data } = await axios.post('/admin/monthly-plans/generate', {
            center_id: filters.value.center_id,
            group_id: filters.value.group_id,
            month: filters.value.month,
            year: filters.value.year,
        });
        appToast.success(data?.message ?? t('monthlyPlans.generated'));
        await fetchPlans();
    } catch (error) {
        appToast.fromAxiosError(error, {
            summary: t('notifications.requestFailedTitle'),
            fallback: t('monthlyPlans.generateFailed'),
        });
    } finally {
        generating.value = false;
    }
};

const onCenterChange = () => {
    if (!groupOptions.value.some((group) => Number(group.id) === Number(filters.value.group_id))) {
        filters.value.group_id = null;
    }
    dates.value = [];
    plans.value = [];
    selectedCenterPayload.value = null;
};

onMounted(() => {
    fetchPlans();
});
</script>

<template>
    <Head :title="t('monthlyPlans.title')" />

    <AdminLayout :nav-items="adminNavItems" :page-title="t('monthlyPlans.title')">
        <section class="space-y-6">
            <AdminBreadcrumbs />

            <article class="rounded-(--radius-base) border border-(--border) bg-(--card) p-5 shadow-(--shadow-sm)">
                <div class="grid gap-3 md:grid-cols-5">
                    <Select
                        v-model="filters.center_id"
                        :options="centers"
                        option-label="name"
                        option-value="id"
                        show-clear
                        filter
                        :placeholder="t('monthlyPlans.center')"
                        class="h-11 w-full"
                        @update:model-value="onCenterChange"
                    />
                    <Select
                        v-model="filters.group_id"
                        :options="groupOptions"
                        option-label="name"
                        option-value="id"
                        show-clear
                        filter
                        :placeholder="t('monthlyPlans.group')"
                        class="h-11 w-full"
                    />
                    <Select v-model="filters.month" :options="monthOptions" option-label="label" option-value="value" class="h-11 w-full" />
                    <Select v-model="filters.year" :options="yearOptions" option-label="label" option-value="value" class="h-11 w-full" />
                    <div class="flex flex-wrap gap-2">
                        <Button type="button" icon="pi pi-search" :label="t('common.search')" severity="secondary" :loading="loading" @click="fetchPlans" />
                        <Button type="button" icon="pi pi-calendar-plus" :label="t('monthlyPlans.generate')" :loading="generating" @click="generatePlans" />
                    </div>
                </div>
                <p v-if="selectedCenter" class="mt-3 text-sm text-(--muted-foreground)">
                    {{ t('monthlyPlans.generateHint', { center: selectedCenter.name }) }}
                    <span v-if="selectedGroup"> / {{ selectedGroup.name }}</span>
                </p>
            </article>

            <div v-if="loading" class="rounded-md border border-(--border) bg-(--card) p-6 text-sm text-(--muted-foreground)">
                {{ t('common.loading') }}
            </div>

            <div v-else-if="plans.length === 0" class="rounded-md border border-dashed border-(--border) bg-(--card) p-8 text-sm text-(--muted-foreground)">
                {{ t('monthlyPlans.noPlans') }}
            </div>

            <article v-else class="rounded-(--radius-base) border border-(--border) bg-(--card) p-5 shadow-(--shadow-sm)">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-semibold">{{ selectedCenterPayload?.name ?? selectedCenter?.name ?? t('monthlyPlans.title') }}</h2>
                        <p class="mt-1 text-sm text-(--muted-foreground)">
                            {{ t('monthlyPlans.monthlyGridHint') }}
                        </p>
                    </div>
                    <div class="flex flex-wrap gap-2 text-sm">
                        <span class="rounded-md border border-(--border) px-3 py-1 font-semibold">
                            {{ t('monthlyPlans.studentsCount') }}: {{ plans.length }}
                        </span>
                        <span class="rounded-md border border-(--border) px-3 py-1 font-semibold">
                            {{ t('monthlyPlans.workingDaysCount') }}: {{ dates.length }}
                        </span>
                    </div>
                </div>

                <div class="mt-4 overflow-x-auto">
                    <table dir="rtl" class="min-w-full border-separate border-spacing-0 text-sm">
                        <thead>
                            <tr>
                                <th class="sticky right-0 z-20 min-w-56 border-b border-(--border) bg-(--card) px-3 py-3 text-start font-semibold">
                                    {{ t('monthlyPlans.student') }}
                                </th>
                                <th
                                    v-for="date in dates"
                                    :key="date.date"
                                    class="border-b border-(--border) px-2 py-2 text-start align-top font-semibold"
                                >
                                    <span class="block">{{ shortDate(date.date) }}</span>
                                    <span class="mt-1 block text-xs font-medium text-(--muted-foreground)">{{ date.day_label }}</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="plan in planRows" :key="plan.id" class="align-top">
                                <th class="sticky right-0 z-10 min-w-56 border-b border-(--border) bg-(--card) px-3 py-3 text-start">
                                    <span class="block font-semibold">{{ plan.student_name }}</span>
                                    <span class="mt-1 block text-xs font-medium text-(--muted-foreground)">
                                        {{ plan.plan_name || t('common.na') }}
                                    </span>
                                    <span class="mt-1 block text-xs text-(--muted-foreground)">
                                        {{ t('monthlyPlans.maxDailyWeight') }}: {{ plan.max_daily_weight }}
                                    </span>
                                </th>
                                <td
                                    v-for="date in dates"
                                    :key="`${plan.id}-${date.date}`"
                                    class="border-b border-(--border) px-1.5 py-1.5 align-top"
                                >
                                    <div v-if="cellItems(plan, date.date).length" class="grid gap-1">
                                        <div
                                            v-for="item in cellItems(plan, date.date)"
                                            :key="item.id"
                                            class="rounded-sm border border-(--border) bg-(--background) px-1.5 py-1"
                                        >
                                            <div class="line-clamp-1 text-xs font-semibold leading-4">{{ item.name }}</div>
                                            <div class="mt-0.5 flex flex-wrap items-center gap-1">
                                                <span class="rounded-sm bg-(--muted) px-1.5 py-0.5 text-[11px] font-semibold">
                                                    {{ item.weight }}
                                                </span>
                                                <span v-if="item.is_standalone" class="rounded-sm bg-amber-100 px-1.5 py-0.5 text-[11px] font-semibold text-amber-900">
                                                    {{ t('monthlyPlans.standalone') }}
                                                </span>
                                            </div>
                                        </div>
                                        <div class="text-[11px] font-semibold text-(--muted-foreground)">
                                            {{ t('monthlyPlans.totalWeight') }}: {{ cellTotalWeight(plan, date.date) }}
                                        </div>
                                    </div>
                                    <div v-else />
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="mt-5 grid gap-3">
                    <div
                        v-for="plan in planRows.filter((row) => row.skipped_items?.length)"
                        :key="`skipped-${plan.id}`"
                        class="rounded-md border border-amber-300 bg-amber-50 p-3 text-sm text-amber-950"
                    >
                        <div>
                            <p class="font-semibold">{{ plan.student_name }} / {{ t('monthlyPlans.skippedItems') }}</p>
                        </div>
                        <div class="mt-2 flex flex-wrap gap-2">
                            <span
                                v-for="item in plan.skipped_items"
                                :key="item.id"
                                class="rounded-md border border-amber-300 bg-white px-2 py-1"
                            >
                                {{ item.name }} / {{ t('monthlyPlans.weight') }}: {{ item.weight }}
                            </span>
                        </div>
                    </div>
                </div>
            </article>
        </section>
    </AdminLayout>
</template>
