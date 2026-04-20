<script setup>
import { Head, router, useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { adminNavItems } from '../../../admin/navItems';
import AdminBreadcrumbs from '../../../components/admin/AdminBreadcrumbs.vue';
import AdminLayout from '../../../components/admin/AdminLayout.vue';
import EvaluationFormCard from '../../../components/admin/EvaluationFormCard.vue';

const props = defineProps({
    evaluation: {
        type: Object,
        required: true,
    },
    centers: {
        type: Array,
        default: () => [],
    },
    students: {
        type: Array,
        default: () => [],
    },
});

const mapStudents = (rows = []) => rows.map((row) => {
    const normalizeScore = (value) => {
        const parsed = Number(value ?? 10);
        if (Number.isNaN(parsed)) {
            return 10;
        }

        return Math.min(10, Math.max(0, parsed));
    };
    const attendance = Number(row.attendances ?? 1);
    const normalizedAttendance = attendance === 5 ? 1 : attendance;
    const normalizedAlhifz = normalizeScore(row.alhifz);
    const normalizedWarud = normalizeScore(row.warud);
    const normalizedAkhlaqi = normalizeScore(row.akhlaqi);
    const normalizedTajwid = normalizeScore(row.tajwid);
    const normalizedNote = String(row.note ?? '').trim();
    const isDefaultEntry = normalizedAttendance === 1
        && normalizedAlhifz === 10
        && normalizedWarud === 10
        && normalizedAkhlaqi === 10
        && normalizedTajwid === 10
        && normalizedNote === '';
    const normalizedRow = {
        student_id: Number(row.student_id),
        full_name: row.full_name ?? '',
        plan_name: row.plan_name ?? null,
        group_name: row.group_name ?? null,
        alhifz: normalizedAlhifz,
        warud: normalizedWarud,
        akhlaqi: normalizedAkhlaqi,
        tajwid: normalizedTajwid,
        note: row.note ?? '',
        attendances: normalizedAttendance,
        was_edited: Boolean(row.was_edited ?? false),
        is_default_entry: isDefaultEntry,
    };

    return {
        ...normalizedRow,
        _baseline: {
            attendances: normalizedRow.attendances,
            alhifz: normalizedRow.alhifz,
            warud: normalizedRow.warud,
            akhlaqi: normalizedRow.akhlaqi,
            tajwid: normalizedRow.tajwid,
            note: normalizedRow.note,
        },
    };
});

const form = useForm({
    center_id: props.evaluation.center_id,
    date: props.evaluation.date,
    items: mapStudents(props.students),
});

const { t } = useI18n();

const submit = () => {
    form.put(`/admin/evaluations/${props.evaluation.id}`);
};

const goBack = () => {
    router.get('/admin/evaluations');
};
</script>

<template>
    <Head :title="t('evaluations.editEvaluation')" />

    <AdminLayout :nav-items="adminNavItems" :page-title="t('evaluations.editEvaluation')">
        <section class="space-y-6">
            <AdminBreadcrumbs />

            <EvaluationFormCard
                :form="form"
                :centers="centers"
                :submit-label="t('common.saveChanges')"
                :title="t('evaluations.editEvaluation')"
                :description="t('evaluations.editDescription')"
                lock-center-and-date
                highlight-unchanged
                @submit="submit"
                @cancel="goBack"
            />
        </section>
    </AdminLayout>
</template>
