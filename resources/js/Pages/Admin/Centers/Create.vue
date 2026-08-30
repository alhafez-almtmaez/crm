<script setup>
import { Head, router, useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { adminNavItems } from '../../../admin/navItems';
import AdminBreadcrumbs from '../../../components/admin/AdminBreadcrumbs.vue';
import AdminLayout from '../../../components/admin/AdminLayout.vue';
import CenterFormCard from '../../../components/admin/CenterFormCard.vue';

const form = useForm({
    name: '',
    student_gender: 'male',
    certificate_name: '',
    show_center_manager_signature: true,
    phone: '',
});
const { t } = useI18n();

const submit = () => {
    form.post('/admin/centers');
};

const goBack = () => {
    router.get('/admin/centers');
};
</script>

<template>
    <Head :title="t('centers.createCenter')" />

    <AdminLayout :nav-items="adminNavItems" :page-title="t('centers.createCenter')">
        <section class="space-y-6">
            <AdminBreadcrumbs />

            <CenterFormCard
                :form="form"
                :submit-label="t('centers.createCenter')"
                :title="t('centers.newCenter')"
                :description="t('centers.createDescription')"
                @submit="submit"
                @cancel="goBack"
            />
        </section>
    </AdminLayout>
</template>
