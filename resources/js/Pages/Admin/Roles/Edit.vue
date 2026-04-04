<script setup>
import { Head, router, useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { adminNavItems } from '../../../admin/navItems';
import AdminBreadcrumbs from '../../../components/admin/AdminBreadcrumbs.vue';
import AdminLayout from '../../../components/admin/AdminLayout.vue';
import RoleFormCard from '../../../components/admin/RoleFormCard.vue';

const props = defineProps({
    permissions: {
        type: Array,
        default: () => [],
    },
    role: {
        type: Object,
        required: true,
    },
});

const form = useForm({
    name: props.role.name,
    permissions: props.role.permission_ids ?? [],
});
const { t } = useI18n();

const submit = () => {
    form.put('/admin/roles/' + props.role.id);
};

const goBack = () => {
    router.get('/admin/roles');
};
</script>

<template>
    <Head :title="t('roles.editRole')" />

    <AdminLayout :nav-items="adminNavItems" :page-title="t('roles.editRole')">
        <section class="space-y-6">
            <AdminBreadcrumbs />

            <RoleFormCard
                :form="form"
                :permissions="permissions"
                :submit-label="t('common.saveChanges')"
                @submit="submit"
                @cancel="goBack"
            />
        </section>
    </AdminLayout>
</template>
