<script setup>
import { Head, router, useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { adminNavItems } from '../../../admin/navItems';
import AdminBreadcrumbs from '../../../components/admin/AdminBreadcrumbs.vue';
import AdminLayout from '../../../components/admin/AdminLayout.vue';
import GroupFormCard from '../../../components/admin/GroupFormCard.vue';

const props = defineProps({
    centers: {
        type: Array,
        default: () => [],
    },
    group: {
        type: Object,
        required: true,
    },
});

const form = useForm({
    name: props.group.name,
    center_id: props.group.center_id,
});
const { t } = useI18n();

const submit = () => {
    form.put('/admin/groups/' + props.group.id);
};

const goBack = () => {
    router.get('/admin/groups');
};
</script>

<template>
    <Head :title="t('groups.editGroup')" />

    <AdminLayout :nav-items="adminNavItems" :page-title="t('groups.editGroup')">
        <section class="space-y-6">
            <AdminBreadcrumbs />

            <GroupFormCard
                :form="form"
                :centers="centers"
                :submit-label="t('common.saveChanges')"
                :title="t('groups.editGroup')"
                :description="t('groups.editDescription')"
                @submit="submit"
                @cancel="goBack"
            />
        </section>
    </AdminLayout>
</template>
