<script setup>
import { Head, router, useForm } from '@inertiajs/vue3';
import Button from 'primevue/button';
import Select from 'primevue/select';
import { computed, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { adminNavItems } from '../../../admin/navItems';
import AdminBreadcrumbs from '../../../components/admin/AdminBreadcrumbs.vue';
import AdminLayout from '../../../components/admin/AdminLayout.vue';

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

const form = useForm({
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
    if (!form.center_id) {
        return props.groups;
    }

    return props.groups.filter((group) => Number(group.center_id) === Number(form.center_id));
});

const selectedCenter = computed(() => props.centers.find((center) => Number(center.id) === Number(form.center_id)) ?? null);

watch(
    () => form.center_id,
    () => {
        if (!groupOptions.value.some((group) => Number(group.id) === Number(form.group_id))) {
            form.group_id = null;
        }
    },
);

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
