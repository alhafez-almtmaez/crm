<script setup>
import { Head, router, useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { adminNavItems } from '../../../admin/navItems';
import AdminBreadcrumbs from '../../../components/admin/AdminBreadcrumbs.vue';
import AdminLayout from '../../../components/admin/AdminLayout.vue';
import RoleFormCard from '../../../components/admin/RoleFormCard.vue';

defineProps({
    permissions: {
        type: Array,
        default: () => [],
    },
});

const form = useForm({
    name: '',
    permissions: [],
});
const { t } = useI18n();

const submit = () => {
    form.post('/admin/roles');
};

const goBack = () => {
    router.get('/admin/roles');
};
</script>

<template>
    <Head :title="t('roles.createRole')" />

    <AdminLayout :nav-items="adminNavItems" :page-title="t('roles.createRole')">
        <section class="space-y-6">
            <AdminBreadcrumbs />

            <RoleFormCard
                :form="form"
                :permissions="permissions"
                :submit-label="t('roles.createRole')"
                @submit="submit"
                @cancel="goBack"
            />
        </section>
    </AdminLayout>
</template>
