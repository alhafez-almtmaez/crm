<script setup>
import { Head, router, useForm } from '@inertiajs/vue3';
import Button from 'primevue/button';
import DatePicker from 'primevue/datepicker';
import FloatLabel from 'primevue/floatlabel';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { adminNavItems } from '../../../admin/navItems';
import AdminBreadcrumbs from '../../../components/admin/AdminBreadcrumbs.vue';
import AdminLayout from '../../../components/admin/AdminLayout.vue';
import MonthlyPlanGrid from '../../../components/admin/MonthlyPlanGrid.vue';
import FormFieldLabel from '../../../components/form/FormFieldLabel.vue';

const props = defineProps({
    monthly_plan: {
        type: Object,
        required: true,
    },
    dates: {
        type: Array,
        default: () => [],
    },
    plans: {
        type: Array,
        default: () => [],
    },
});

const { t } = useI18n();
const refreshForm = useForm({
    from_date: props.monthly_plan.refresh_from_date ?? '',
    holiday_dates: props.monthly_plan.holiday_dates ?? [],
});

const title = computed(() => `${props.monthly_plan.group_name} / ${t(`monthlyPlans.months.${props.monthly_plan.month}`)} ${props.monthly_plan.year}`);
const parseYmdDate = (value) => {
    if (!value || typeof value !== 'string') {
        return null;
    }

    const parts = value.split('-').map((segment) => Number(segment));
    if (parts.length !== 3 || parts.some((part) => Number.isNaN(part))) {
        return null;
    }

    return new Date(parts[0], parts[1] - 1, parts[2]);
};
const formatYmdDate = (value) => {
    const year = value.getFullYear();
    const month = String(value.getMonth() + 1).padStart(2, '0');
    const day = String(value.getDate()).padStart(2, '0');

    return `${year}-${month}-${day}`;
};
const normalizeYmdDateList = (values) => [...new Set((values ?? [])
    .filter((value) => value instanceof Date && !Number.isNaN(value.getTime()))
    .map((value) => formatYmdDate(value)))].sort();
const refreshDateValue = computed({
    get: () => parseYmdDate(refreshForm.from_date),
    set: (value) => {
        if (!(value instanceof Date) || Number.isNaN(value.getTime())) {
            refreshForm.from_date = '';
            return;
        }

        refreshForm.from_date = formatYmdDate(value);
    },
});
const refreshMinDate = computed(() => parseYmdDate(props.monthly_plan.refresh_min_date));
const refreshMaxDate = computed(() => parseYmdDate(props.monthly_plan.refresh_max_date));
const holidayDateValues = computed({
    get: () => (refreshForm.holiday_dates ?? [])
        .map((date) => parseYmdDate(date))
        .filter((date) => date !== null),
    set: (values) => {
        refreshForm.holiday_dates = normalizeYmdDateList(Array.isArray(values) ? values : []);
    },
});
const selectedHolidayDates = computed(() => [...(refreshForm.holiday_dates ?? [])].sort());
const holidayDateErrors = computed(() => Object.entries(refreshForm.errors)
    .filter(([key]) => key === 'holiday_dates' || key.startsWith('holiday_dates.'))
    .map(([, message]) => message));

const goBack = () => {
    router.get('/admin/monthly-plans');
};

const removeHolidayDate = (date) => {
    refreshForm.holiday_dates = (refreshForm.holiday_dates ?? []).filter((holidayDate) => holidayDate !== date);
};

const clearHolidayDates = () => {
    refreshForm.holiday_dates = [];
};

const refreshFuturePlan = () => {
    if (!window.confirm(t('monthlyPlans.refreshFutureConfirm', { date: refreshForm.from_date }))) {
        return;
    }

    refreshForm.post(`/admin/monthly-plans/${props.monthly_plan.id}/refresh-future`, {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head :title="title" />

    <AdminLayout :nav-items="adminNavItems" :page-title="t('monthlyPlans.savedPlanDetails')">
        <section class="space-y-6">
            <AdminBreadcrumbs />

            <article class="rounded-(--radius-base) border border-(--border) bg-(--card) p-5 shadow-(--shadow-sm)">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-semibold">{{ title }}</h2>
                        <p class="mt-1 text-sm text-(--muted-foreground)">
                            {{ monthly_plan.center_name }}
                        </p>
                    </div>
                    <Button type="button" icon="pi pi-arrow-left" :label="t('monthlyPlans.backToSavedPlans')" severity="secondary" outlined @click="goBack" />
                </div>

                <div class="mt-4 grid gap-3 md:grid-cols-6">
                    <div class="rounded-md border border-(--border) px-3 py-2">
                        <span class="block text-xs text-(--muted-foreground)">{{ t('monthlyPlans.period') }}</span>
                        <span class="mt-1 block font-semibold">{{ monthly_plan.period_label }}</span>
                    </div>
                    <div class="rounded-md border border-(--border) px-3 py-2">
                        <span class="block text-xs text-(--muted-foreground)">{{ t('monthlyPlans.holidayDates') }}</span>
                        <span class="mt-1 block font-semibold">{{ monthly_plan.holidays_count }}</span>
                    </div>
                    <div class="rounded-md border border-(--border) px-3 py-2">
                        <span class="block text-xs text-(--muted-foreground)">{{ t('monthlyPlans.studentsCount') }}</span>
                        <span class="mt-1 block font-semibold">{{ monthly_plan.students_count }}</span>
                    </div>
                    <div class="rounded-md border border-(--border) px-3 py-2">
                        <span class="block text-xs text-(--muted-foreground)">{{ t('monthlyPlans.itemsCount') }}</span>
                        <span class="mt-1 block font-semibold">{{ monthly_plan.generated_items_count }}</span>
                    </div>
                    <div class="rounded-md border border-(--border) px-3 py-2">
                        <span class="block text-xs text-(--muted-foreground)">{{ t('monthlyPlans.skippedItems') }}</span>
                        <span class="mt-1 block font-semibold">{{ monthly_plan.skipped_items_count }}</span>
                    </div>
                    <div class="rounded-md border border-(--border) px-3 py-2">
                        <span class="block text-xs text-(--muted-foreground)">{{ t('monthlyPlans.generatedAt') }}</span>
                        <span class="mt-1 block font-semibold">{{ monthly_plan.generated_at }}</span>
                    </div>
                </div>

                <form class="mt-5 border-t border-(--border) pt-5" @submit.prevent="refreshFuturePlan">
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <h3 class="text-sm font-semibold">{{ t('monthlyPlans.refreshFutureTitle') }}</h3>
                            <p class="mt-1 text-sm text-(--muted-foreground)">
                                {{ t('monthlyPlans.refreshFutureHint') }}
                            </p>
                        </div>

                        <div class="flex w-full flex-wrap items-start gap-3 md:w-auto">
                            <div class="flex w-full min-w-64 flex-col gap-1 md:w-72">
                                <FloatLabel variant="on">
                                    <DatePicker
                                        input-id="monthly-plan-refresh-from-date"
                                        v-model="refreshDateValue"
                                        show-icon
                                        icon-display="input"
                                        date-format="yy-mm-dd"
                                        :min-date="refreshMinDate"
                                        :max-date="refreshMaxDate"
                                        :manual-input="false"
                                        class="h-11 w-full rounded-md border border-(--border) bg-(--background) text-(--foreground) shadow-none"
                                    />
                                    <FormFieldLabel for-id="monthly-plan-refresh-from-date" :text="t('monthlyPlans.refreshFromDate')" required />
                                </FloatLabel>
                                <small v-if="refreshForm.errors.from_date" class="text-sm text-red-600">{{ refreshForm.errors.from_date }}</small>
                            </div>

                            <Button
                                type="submit"
                                icon="pi pi-refresh"
                                :label="t('monthlyPlans.refreshFuture')"
                                :loading="refreshForm.processing"
                            />
                        </div>
                    </div>

                    <div class="mt-4 grid gap-2">
                        <FloatLabel variant="on">
                            <DatePicker
                                input-id="monthly-plan-refresh-holiday-dates"
                                v-model="holidayDateValues"
                                selection-mode="multiple"
                                show-icon
                                icon-display="input"
                                date-format="yy-mm-dd"
                                :min-date="refreshMinDate"
                                :max-date="refreshMaxDate"
                                :manual-input="false"
                                class="h-11 w-full rounded-md border border-(--border) bg-(--background) text-(--foreground) shadow-none"
                            />
                            <FormFieldLabel for-id="monthly-plan-refresh-holiday-dates" :text="t('monthlyPlans.holidayDates')" />
                        </FloatLabel>

                        <div v-if="selectedHolidayDates.length" class="flex flex-wrap items-center gap-2">
                            <span
                                v-for="date in selectedHolidayDates"
                                :key="date"
                                class="inline-flex h-8 items-center gap-2 rounded-md border border-(--border) bg-(--muted) px-2 text-sm text-(--foreground)"
                            >
                                <span>{{ date }}</span>
                                <button
                                    type="button"
                                    class="inline-flex size-5 items-center justify-center rounded-sm text-(--muted-foreground) hover:bg-(--background) hover:text-(--foreground)"
                                    :aria-label="t('monthlyPlans.removeHolidayDate')"
                                    @click="removeHolidayDate(date)"
                                >
                                    <i class="pi pi-times text-xs" aria-hidden="true" />
                                </button>
                            </span>
                            <Button
                                type="button"
                                icon="pi pi-trash"
                                severity="secondary"
                                text
                                :aria-label="t('monthlyPlans.clearHolidayDates')"
                                @click="clearHolidayDates"
                            />
                        </div>
                        <p v-else class="text-sm text-(--muted-foreground)">{{ t('monthlyPlans.noHolidayDates') }}</p>
                        <small v-for="error in holidayDateErrors" :key="error" class="text-sm text-red-600">{{ error }}</small>
                    </div>
                </form>
            </article>

            <MonthlyPlanGrid :dates="dates" :plans="plans" />
        </section>
    </AdminLayout>
</template>
