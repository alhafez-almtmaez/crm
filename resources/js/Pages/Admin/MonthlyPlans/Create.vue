<script setup>
import { Head, router, useForm } from '@inertiajs/vue3';
import Button from 'primevue/button';
import DatePicker from 'primevue/datepicker';
import FloatLabel from 'primevue/floatlabel';
import Select from 'primevue/select';
import { computed, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { adminNavItems } from '../../../admin/navItems';
import AdminBreadcrumbs from '../../../components/admin/AdminBreadcrumbs.vue';
import AdminLayout from '../../../components/admin/AdminLayout.vue';
import FormFieldLabel from '../../../components/form/FormFieldLabel.vue';

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
    default_start_date: {
        type: String,
        default: '',
    },
    default_end_date: {
        type: String,
        default: '',
    },
});

const { t } = useI18n();

const form = useForm({
    center_id: null,
    group_id: null,
    month: props.default_month,
    year: props.default_year,
    start_date: props.default_start_date,
    end_date: props.default_end_date,
    holiday_dates: [],
});

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

const isYmdDateWithinRange = (value, minDate, maxDate) => {
    const date = parseYmdDate(value);

    return date !== null && date >= minDate && date <= maxDate;
};

const selectedMonthStartDate = computed(() => new Date(Number(form.year), Number(form.month) - 1, 1));
const selectedMonthEndDate = computed(() => new Date(Number(form.year), Number(form.month), 0));

const startDateValue = computed({
    get: () => parseYmdDate(form.start_date),
    set: (value) => {
        if (!(value instanceof Date) || Number.isNaN(value.getTime())) {
            form.start_date = '';
            return;
        }

        form.start_date = formatYmdDate(value);
    },
});

const endDateValue = computed({
    get: () => parseYmdDate(form.end_date),
    set: (value) => {
        if (!(value instanceof Date) || Number.isNaN(value.getTime())) {
            form.end_date = '';
            return;
        }

        form.end_date = formatYmdDate(value);
    },
});

const holidayMinDate = computed(() => startDateValue.value ?? selectedMonthStartDate.value);
const holidayMaxDate = computed(() => endDateValue.value ?? selectedMonthEndDate.value);
const holidayDateValues = computed({
    get: () => (form.holiday_dates ?? [])
        .map((date) => parseYmdDate(date))
        .filter((date) => date !== null),
    set: (values) => {
        form.holiday_dates = normalizeYmdDateList(Array.isArray(values) ? values : []);
    },
});
const holidayDateErrors = computed(() => Object.entries(form.errors)
    .filter(([key]) => key === 'holiday_dates' || key.startsWith('holiday_dates.'))
    .map(([, message]) => message));

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
    if (!form.center_id) {
        return props.groups;
    }

    return props.groups.filter((group) => Number(group.center_id) === Number(form.center_id));
});

const selectedCenter = computed(() => props.centers.find((center) => Number(center.id) === Number(form.center_id)) ?? null);
const selectedHolidayDates = computed(() => [...(form.holiday_dates ?? [])].sort());

watch(
    () => form.center_id,
    () => {
        if (!groupOptions.value.some((group) => Number(group.id) === Number(form.group_id))) {
            form.group_id = null;
        }
    },
);

watch(
    () => [form.month, form.year],
    () => {
        form.start_date = formatYmdDate(selectedMonthStartDate.value);
        form.end_date = formatYmdDate(selectedMonthEndDate.value);
        form.holiday_dates = [];
    },
);

watch(
    () => [form.start_date, form.end_date],
    () => {
        const startDate = parseYmdDate(form.start_date);
        const endDate = parseYmdDate(form.end_date);

        if (startDate !== null && endDate !== null && startDate > endDate) {
            form.end_date = form.start_date;
            return;
        }

        form.holiday_dates = (form.holiday_dates ?? [])
            .filter((date) => isYmdDateWithinRange(date, holidayMinDate.value, holidayMaxDate.value));
    },
);

const removeHolidayDate = (date) => {
    form.holiday_dates = (form.holiday_dates ?? []).filter((holidayDate) => holidayDate !== date);
};

const clearHolidayDates = () => {
    form.holiday_dates = [];
};

const submit = () => {
    form.post('/admin/monthly-plans');
};

const goBack = () => {
    router.get('/admin/monthly-plans');
};
</script>

<template>
    <Head :title="t('monthlyPlans.createPlan')" />

    <AdminLayout :nav-items="adminNavItems" :page-title="t('monthlyPlans.createPlan')">
        <section class="space-y-6">
            <AdminBreadcrumbs />

            <article class="rounded-(--radius-base) border border-(--border) bg-(--card) p-5 shadow-(--shadow-sm)">
                <div class="mb-5">
                    <h2 class="text-lg font-semibold">{{ t('monthlyPlans.newPlan') }}</h2>
                    <p class="mt-1 text-sm text-(--muted-foreground)">{{ t('monthlyPlans.createDescription') }}</p>
                </div>

                <form class="grid gap-4" @submit.prevent="submit">
                    <div class="grid gap-3 md:grid-cols-2">
                        <div>
                            <Select
                                v-model="form.center_id"
                                :options="centers"
                                option-label="name"
                                option-value="id"
                                filter
                                :placeholder="t('monthlyPlans.center')"
                                class="h-11 w-full"
                                :invalid="Boolean(form.errors.center_id)"
                            />
                            <p v-if="form.errors.center_id" class="mt-1 text-sm text-red-600">{{ form.errors.center_id }}</p>
                        </div>

                        <div>
                            <Select
                                v-model="form.group_id"
                                :options="groupOptions"
                                option-label="name"
                                option-value="id"
                                show-clear
                                filter
                                :placeholder="t('monthlyPlans.groupOptional')"
                                class="h-11 w-full"
                                :disabled="!form.center_id"
                                :invalid="Boolean(form.errors.group_id)"
                            />
                            <p v-if="form.errors.group_id" class="mt-1 text-sm text-red-600">{{ form.errors.group_id }}</p>
                        </div>

                        <div>
                            <Select
                                v-model="form.month"
                                :options="monthOptions"
                                option-label="label"
                                option-value="value"
                                class="h-11 w-full"
                                :invalid="Boolean(form.errors.month)"
                            />
                            <p v-if="form.errors.month" class="mt-1 text-sm text-red-600">{{ form.errors.month }}</p>
                        </div>

                        <div>
                            <Select
                                v-model="form.year"
                                :options="yearOptions"
                                option-label="label"
                                option-value="value"
                                class="h-11 w-full"
                                :invalid="Boolean(form.errors.year)"
                            />
                            <p v-if="form.errors.year" class="mt-1 text-sm text-red-600">{{ form.errors.year }}</p>
                        </div>

                        <div class="flex flex-col gap-1">
                            <FloatLabel variant="on">
                                <DatePicker
                                    input-id="monthly-plan-start-date"
                                    v-model="startDateValue"
                                    show-icon
                                    icon-display="input"
                                    date-format="yy-mm-dd"
                                    :min-date="selectedMonthStartDate"
                                    :max-date="selectedMonthEndDate"
                                    :manual-input="false"
                                    class="h-11 w-full rounded-md border border-(--border) bg-(--background) text-(--foreground) shadow-none"
                                />
                                <FormFieldLabel for-id="monthly-plan-start-date" :text="t('monthlyPlans.startDate')" required />
                            </FloatLabel>
                            <p v-if="form.errors.start_date" class="text-sm text-red-600">{{ form.errors.start_date }}</p>
                        </div>

                        <div class="flex flex-col gap-1">
                            <FloatLabel variant="on">
                                <DatePicker
                                    input-id="monthly-plan-end-date"
                                    v-model="endDateValue"
                                    show-icon
                                    icon-display="input"
                                    date-format="yy-mm-dd"
                                    :min-date="startDateValue ?? selectedMonthStartDate"
                                    :max-date="selectedMonthEndDate"
                                    :manual-input="false"
                                    class="h-11 w-full rounded-md border border-(--border) bg-(--background) text-(--foreground) shadow-none"
                                />
                                <FormFieldLabel for-id="monthly-plan-end-date" :text="t('monthlyPlans.endDate')" required />
                            </FloatLabel>
                            <p v-if="form.errors.end_date" class="text-sm text-red-600">{{ form.errors.end_date }}</p>
                        </div>

                        <div class="flex flex-col gap-2 md:col-span-2">
                            <FloatLabel variant="on">
                                <DatePicker
                                    input-id="monthly-plan-holiday-dates"
                                    v-model="holidayDateValues"
                                    selection-mode="multiple"
                                    show-icon
                                    icon-display="input"
                                    date-format="yy-mm-dd"
                                    :min-date="holidayMinDate"
                                    :max-date="holidayMaxDate"
                                    :manual-input="false"
                                    class="h-11 w-full rounded-md border border-(--border) bg-(--background) text-(--foreground) shadow-none"
                                />
                                <FormFieldLabel for-id="monthly-plan-holiday-dates" :text="t('monthlyPlans.holidayDates')" />
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
                            <p v-for="error in holidayDateErrors" :key="error" class="text-sm text-red-600">{{ error }}</p>
                        </div>
                    </div>

                    <p v-if="selectedCenter" class="text-sm text-(--muted-foreground)">
                        {{ t('monthlyPlans.generateHint', { center: selectedCenter.name }) }}
                    </p>

                    <div class="flex flex-wrap gap-2">
                        <Button type="submit" icon="pi pi-plus" :label="t('monthlyPlans.createPlan')" :loading="form.processing" />
                        <Button type="button" icon="pi pi-times" :label="t('common.cancel')" severity="secondary" outlined @click="goBack" />
                    </div>
                </form>
            </article>
        </section>
    </AdminLayout>
</template>
