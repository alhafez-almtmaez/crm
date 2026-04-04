<script setup>
import { Head, router, useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { adminNavItems } from '../../../admin/navItems';
import AdminBreadcrumbs from '../../../components/admin/AdminBreadcrumbs.vue';
import AdminLayout from '../../../components/admin/AdminLayout.vue';
import PlanFormCard from '../../../components/admin/PlanFormCard.vue';

const props = defineProps({
    plan: {
        type: Object,
        required: true,
    },
});

const form = useForm({
    name: props.plan.name,
});
const { t } = useI18n();

const submit = () => {
    form.put('/admin/plans/' + props.plan.id);
};

const goBack = () => {
    router.get('/admin/plans');
};
</script>

<template>
    <Head :title="t('plans.editPlan')" />

    <AdminLayout :nav-items="adminNavItems" :page-title="t('plans.editPlan')">
        <section class="space-y-6">
            <AdminBreadcrumbs />

            <PlanFormCard
                :form="form"
                :submit-label="t('common.saveChanges')"
                :title="t('plans.editPlan')"
                :description="t('plans.editDescription')"
                @submit="submit"
                @cancel="goBack"
            />
        </section>
    </AdminLayout>
</template>
