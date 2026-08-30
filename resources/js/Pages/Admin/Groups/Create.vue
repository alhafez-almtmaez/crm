<script setup>
import { Head, router, useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { adminNavItems } from '../../../admin/navItems';
import AdminBreadcrumbs from '../../../components/admin/AdminBreadcrumbs.vue';
import AdminLayout from '../../../components/admin/AdminLayout.vue';
import GroupFormCard from '../../../components/admin/GroupFormCard.vue';

defineProps({
    centers: {
        type: Array,
        default: () => [],
    },
    whatsappGroups: {
        type: Array,
        default: () => [],
    },
});

const form = useForm({
    name: '',
    center_id: null,
    group_serialized: '',
    working_days: [],
});
const { t } = useI18n();

const submit = () => {
    form.post('/admin/groups');
};

const goBack = () => {
    router.get('/admin/groups');
};
</script>

<template>
    <Head :title="t('groups.createGroup')" />

    <AdminLayout :nav-items="adminNavItems" :page-title="t('groups.createGroup')">
        <section class="space-y-6">
            <AdminBreadcrumbs />

            <GroupFormCard
                :form="form"
                :centers="centers"
                :whatsapp-groups="whatsappGroups"
                :submit-label="t('groups.createGroup')"
                :title="t('groups.newGroup')"
                :description="t('groups.createDescription')"
                @submit="submit"
                @cancel="goBack"
            />
        </section>
    </AdminLayout>
</template>
