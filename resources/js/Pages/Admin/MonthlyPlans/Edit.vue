<script setup>
import { Head, router } from '@inertiajs/vue3';
import Button from 'primevue/button';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { adminNavItems } from '../../../admin/navItems';
import AdminBreadcrumbs from '../../../components/admin/AdminBreadcrumbs.vue';
import AdminLayout from '../../../components/admin/AdminLayout.vue';
import MonthlyPlanGrid from '../../../components/admin/MonthlyPlanGrid.vue';

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

const title = computed(() => `${props.monthly_plan.group_name} / ${t(`monthlyPlans.months.${props.monthly_plan.month}`)} ${props.monthly_plan.year}`);

const goBack = () => {
    router.get('/admin/monthly-plans');
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

                <div class="mt-4 grid gap-3 md:grid-cols-4">
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
            </article>

            <MonthlyPlanGrid :dates="dates" :plans="plans" />
        </section>
    </AdminLayout>
</template>
