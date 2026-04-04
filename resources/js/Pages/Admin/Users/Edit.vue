<script setup>
import { Head, router, useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { adminNavItems } from '../../../admin/navItems';
import AdminBreadcrumbs from '../../../components/admin/AdminBreadcrumbs.vue';
import AdminLayout from '../../../components/admin/AdminLayout.vue';
import UserFormCard from '../../../components/admin/UserFormCard.vue';

const props = defineProps({
    roles: {
        type: Array,
        default: () => [],
    },
    user: {
        type: Object,
        required: true,
    },
});

const form = useForm({
    name: props.user.name,
    email: props.user.email,
    role_id: props.user.role_id ?? null,
    password: '',
    password_confirmation: '',
});
const { t } = useI18n();

const submit = () => {
    form.put(`/admin/users/${props.user.id}`);
};

const goBack = () => {
    router.get('/admin/users');
};
</script>

<template>
    <Head :title="t('users.editUser')" />

    <AdminLayout :nav-items="adminNavItems" :page-title="t('users.editUser')">
        <section class="space-y-6">
            <AdminBreadcrumbs />

            <UserFormCard
                :submit-label="t('common.saveChanges')"
                :password-label="t('users.newPassword')"
                :form="form"
                :roles="roles"
                :require-password="false"
                @submit="submit"
                @cancel="goBack"
            />
        </section>
    </AdminLayout>
</template>
