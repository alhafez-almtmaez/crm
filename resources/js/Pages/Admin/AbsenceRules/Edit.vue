<script setup>
import { Head, router, useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { adminNavItems } from '../../../admin/navItems';
import AdminBreadcrumbs from '../../../components/admin/AdminBreadcrumbs.vue';
import AdminLayout from '../../../components/admin/AdminLayout.vue';
import AbsenceRuleFormCard from '../../../components/admin/AbsenceRuleFormCard.vue';

const props = defineProps({
    rule: {
        type: Object,
        required: true,
    },
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
    center_id: props.rule.center_id,
    attendance_type: props.rule.attendance_type,
    occurrence_number: props.rule.occurrence_number,
    action: props.rule.action,
    message_template_id: props.rule.message_template_id,
    send_to_center_group: Boolean(props.rule.send_to_center_group),
    freeze_reason: props.rule.freeze_reason ?? '',
    freeze_working_days_count: props.rule.freeze_working_days_count,
    deduction_points_count: props.rule.deduction_points_count ?? 0,
    meta: props.rule.meta ?? null,
    is_active: Boolean(props.rule.is_active),
});

const { t } = useI18n();

const submit = () => {
    form.put(`/admin/absence-rules/${props.rule.id}`);
};

const goBack = () => {
    router.get('/admin/absence-rules');
};
</script>

<template>
    <Head :title="t('absenceRules.editRule')" />

    <AdminLayout :nav-items="adminNavItems" :page-title="t('absenceRules.editRule')">
        <section class="space-y-6">
            <AdminBreadcrumbs />

            <AbsenceRuleFormCard
                :form="form"
                :centers="centers"
                :templates="templates"
                :submit-label="t('common.saveChanges')"
                :title="t('absenceRules.editRule')"
                :description="t('absenceRules.editDescription')"
                @submit="submit"
                @cancel="goBack"
            />
        </section>
    </AdminLayout>
</template>
