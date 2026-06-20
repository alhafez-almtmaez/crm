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

const fetchPlans = async () => {
    loading.value = true;

    try {
        const { data } = await axios.get('/admin/monthly-plans/records', { params: filters.value });
        plans.value = data?.data ?? [];
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
    if (!filters.value.group_id) {
        appToast.error(t('monthlyPlans.groupRequired'));
        return;
    }

    generating.value = true;

    try {
        const { data } = await axios.post('/admin/monthly-plans/generate', {
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
                <p v-if="selectedGroup" class="mt-3 text-sm text-(--muted-foreground)">
                    {{ t('monthlyPlans.generateHint', { group: selectedGroup.name }) }}
                </p>
            </article>

            <div v-if="loading" class="rounded-md border border-(--border) bg-(--card) p-6 text-sm text-(--muted-foreground)">
                {{ t('common.loading') }}
            </div>

            <div v-else-if="plans.length === 0" class="rounded-md border border-dashed border-(--border) bg-(--card) p-8 text-sm text-(--muted-foreground)">
                {{ t('monthlyPlans.noPlans') }}
            </div>

            <div v-else class="grid gap-5">
                <article
                    v-for="plan in plans"
                    :key="plan.id"
                    class="rounded-(--radius-base) border border-(--border) bg-(--card) p-5 shadow-(--shadow-sm)"
                >
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h2 class="text-xl font-semibold">{{ plan.student_name }}</h2>
                            <p class="mt-1 text-sm text-(--muted-foreground)">
                                {{ plan.plan_name || t('common.na') }} / {{ plan.group_name || t('common.na') }}
                            </p>
                        </div>
                        <div class="flex flex-wrap gap-2 text-sm">
                            <span class="rounded-md border border-(--border) px-3 py-1 font-semibold">
                                {{ t('monthlyPlans.maxDailyWeight') }}: {{ plan.max_daily_weight }}
                            </span>
                            <span class="rounded-md border border-(--border) px-3 py-1 font-semibold">
                                {{ t('monthlyPlans.itemsCount') }}: {{ plan.generated_items_count }}
                            </span>
                            <span v-if="plan.skipped_items_count" class="rounded-md border border-amber-300 px-3 py-1 font-semibold text-amber-800">
                                {{ t('monthlyPlans.skippedItems') }}: {{ plan.skipped_items_count }}
                            </span>
                        </div>
                    </div>

                    <div
                        v-if="plan.skipped_items?.length"
                        class="mt-4 rounded-md border border-amber-300 bg-amber-50 p-3 text-sm text-amber-950"
                    >
                        <p class="font-semibold">{{ t('monthlyPlans.skippedItems') }}</p>
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

                    <div class="mt-4 overflow-x-auto">
                        <table class="min-w-full divide-y divide-(--border) text-sm">
                            <thead>
                                <tr>
                                    <th class="px-3 py-2 text-start font-semibold">{{ t('monthlyPlans.date') }}</th>
                                    <th class="px-3 py-2 text-start font-semibold">{{ t('monthlyPlans.totalWeight') }}</th>
                                    <th class="px-3 py-2 text-start font-semibold">{{ t('monthlyPlans.items') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-(--border)">
                                <tr v-for="day in plan.days" :key="day.id">
                                    <td class="whitespace-nowrap px-3 py-3 font-semibold">{{ day.date }}</td>
                                    <td class="px-3 py-3">{{ day.total_weight }}</td>
                                    <td class="px-3 py-3">
                                        <div class="grid gap-2">
                                            <div
                                                v-for="item in day.items"
                                                :key="item.id"
                                                class="rounded-md border border-(--border) bg-(--background) p-2"
                                            >
                                                <div class="flex flex-wrap items-center gap-2">
                                                    <strong>{{ item.name }}</strong>
                                                    <span class="rounded-md bg-(--muted) px-2 py-0.5 text-xs font-semibold">
                                                        {{ t('monthlyPlans.weight') }}: {{ item.weight }}
                                                    </span>
                                                    <span v-if="item.is_standalone" class="rounded-md bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-900">
                                                        {{ t('monthlyPlans.standalone') }}
                                                    </span>
                                                    <span class="rounded-md bg-sky-100 px-2 py-0.5 text-xs font-semibold text-sky-900">
                                                        {{ item.status }}
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </article>
            </div>
        </section>
    </AdminLayout>
</template>
