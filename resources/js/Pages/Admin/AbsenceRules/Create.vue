<script setup>
import { Head, router, useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { adminNavItems } from '../../../admin/navItems';
import AdminBreadcrumbs from '../../../components/admin/AdminBreadcrumbs.vue';
import AdminLayout from '../../../components/admin/AdminLayout.vue';
import AbsenceRuleFormCard from '../../../components/admin/AbsenceRuleFormCard.vue';

defineProps({
    centers: {
        type: Array,
        default: () => [],
    },
    templates: {
        type: Array,
        default: () => [],
    },
});

const form = useForm({
    center_id: null,
    attendance_type: 'absence',
    occurrence_number: 1,
    action: 'freeze_student',
    message_template_id: null,
    send_to_center_group: false,
    freeze_reason: '',
    freeze_working_days_count: 4,
    deduction_points_count: 0,
    meta: null,
    is_active: true,
});

const { t } = useI18n();

const submit = () => {
    form.post('/admin/absence-rules');
};

const goBack = () => {
    router.get('/admin/absence-rules');
};
</script>

<template>
    <Head :title="t('absenceRules.createRule')" />

    <AdminLayout :nav-items="adminNavItems" :page-title="t('absenceRules.createRule')">
        <section class="space-y-6">
            <AdminBreadcrumbs />

            <AbsenceRuleFormCard
                :form="form"
                :centers="centers"
                :templates="templates"
                :submit-label="t('absenceRules.createRule')"
                :title="t('absenceRules.newRule')"
                :description="t('absenceRules.createDescription')"
                @submit="submit"
                @cancel="goBack"
            />
        </section>
    </AdminLayout>
</template>
