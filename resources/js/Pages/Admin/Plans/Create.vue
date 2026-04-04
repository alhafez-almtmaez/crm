<script setup>
import { Head, router, useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { adminNavItems } from '../../../admin/navItems';
import AdminBreadcrumbs from '../../../components/admin/AdminBreadcrumbs.vue';
import AdminLayout from '../../../components/admin/AdminLayout.vue';
import PlanFormCard from '../../../components/admin/PlanFormCard.vue';

const form = useForm({
    name: '',
});
const { t } = useI18n();

const submit = () => {
    form.post('/admin/plans');
};

const goBack = () => {
    router.get('/admin/plans');
};
</script>

<template>
    <Head :title="t('plans.createPlan')" />

    <AdminLayout :nav-items="adminNavItems" :page-title="t('plans.createPlan')">
        <section class="space-y-6">
            <AdminBreadcrumbs />

            <PlanFormCard
                :form="form"
                :submit-label="t('plans.createPlan')"
                :title="t('plans.newPlan')"
                :description="t('plans.createDescription')"
                @submit="submit"
                @cancel="goBack"
            />
        </section>
    </AdminLayout>
</template>
