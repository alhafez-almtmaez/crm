<script setup>
import { Head, router, useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { adminNavItems } from '../../../admin/navItems';
import AdminBreadcrumbs from '../../../components/admin/AdminBreadcrumbs.vue';
import AdminLayout from '../../../components/admin/AdminLayout.vue';
import CenterFormCard from '../../../components/admin/CenterFormCard.vue';

defineProps({
    whatsappGroups: {
        type: Array,
        default: () => [],
    },
});

const form = useForm({
    name: '',
    phone: '',
    group_serialized: '',
    working_days: [],
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
                :whatsapp-groups="whatsappGroups"
                :submit-label="t('centers.createCenter')"
                :title="t('centers.newCenter')"
                :description="t('centers.createDescription')"
                @submit="submit"
                @cancel="goBack"
            />
        </section>
    </AdminLayout>
</template>
