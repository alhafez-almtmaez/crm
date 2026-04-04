<script setup>
import { Head, router, useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { adminNavItems } from '../../../admin/navItems';
import AdminBreadcrumbs from '../../../components/admin/AdminBreadcrumbs.vue';
import AdminLayout from '../../../components/admin/AdminLayout.vue';
import CenterFormCard from '../../../components/admin/CenterFormCard.vue';

const props = defineProps({
    center: {
        type: Object,
        required: true,
    },
    whatsappGroups: {
        type: Array,
        default: () => [],
    },
});

const form = useForm({
    name: props.center.name,
    phone: props.center.phone,
    group_serialized: props.center.group_serialized ?? '',
    working_days: props.center.working_days ?? [],
});
const { t } = useI18n();

const submit = () => {
    form.put('/admin/centers/' + props.center.id);
};

const goBack = () => {
    router.get('/admin/centers');
};
</script>

<template>
    <Head :title="t('centers.editCenter')" />

    <AdminLayout :nav-items="adminNavItems" :page-title="t('centers.editCenter')">
        <section class="space-y-6">
            <AdminBreadcrumbs />

            <CenterFormCard
                :form="form"
                :whatsapp-groups="whatsappGroups"
                :submit-label="t('common.saveChanges')"
                :title="t('centers.editCenter')"
                :description="t('centers.editDescription')"
                @submit="submit"
                @cancel="goBack"
            />
        </section>
    </AdminLayout>
</template>
