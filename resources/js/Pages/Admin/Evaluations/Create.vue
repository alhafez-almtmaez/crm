<script setup>
import { Head, router, useForm } from '@inertiajs/vue3';
import { watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { adminNavItems } from '../../../admin/navItems';
import AdminBreadcrumbs from '../../../components/admin/AdminBreadcrumbs.vue';
import AdminLayout from '../../../components/admin/AdminLayout.vue';
import EvaluationFormCard from '../../../components/admin/EvaluationFormCard.vue';

const props = defineProps({
    centers: {
        type: Array,
        default: () => [],
    },
    selected_center_id: {
        type: Number,
        default: null,
    },
    selected_date: {
        type: String,
        default: '',
    },
    students: {
        type: Array,
        default: () => [],
    },
    existing_evaluation_id: {
        type: Number,
        default: null,
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
    center_id: props.selected_center_id,
    date: props.selected_date,
    items: mapStudents(props.students),
});

watch(
    () => props.selected_center_id,
    (value) => {
        form.center_id = value;
    },
);

watch(
    () => props.selected_date,
    (value) => {
        form.date = value;
    },
);

watch(
    () => props.students,
    (value) => {
        form.items = mapStudents(value);
    },
    { immediate: true },
);

const { t } = useI18n();

const submit = () => {
    form.post('/admin/evaluations');
};

const goBack = () => {
    router.get('/admin/evaluations');
};

const reloadStudents = () => {
    router.get('/admin/evaluations/create', {
        center_id: form.center_id,
        date: form.date,
    });
};

const openExisting = () => {
    if (!props.existing_evaluation_id) {
        return;
    }

    router.get(`/admin/evaluations/${props.existing_evaluation_id}/edit`);
};
</script>

<template>
    <Head :title="t('evaluations.createEvaluation')" />

    <AdminLayout :nav-items="adminNavItems" :page-title="t('evaluations.createEvaluation')">
        <section class="space-y-6">
            <AdminBreadcrumbs />

            <article
                v-if="existing_evaluation_id"
                class="rounded-(--radius-base) border border-amber-300 bg-amber-50 p-4 text-amber-900 dark:border-amber-700 dark:bg-amber-950/30 dark:text-amber-100"
            >
                <p class="font-semibold">{{ t('evaluations.alreadyExists') }}</p>
                <div class="mt-3">
                    <button
                        type="button"
                        class="rounded-md bg-amber-700 px-4 py-2 text-sm text-white"
                        @click="openExisting"
                    >
                        {{ t('evaluations.openExisting') }}
                    </button>
                </div>
            </article>

            <EvaluationFormCard
                :form="form"
                :centers="centers"
                :submit-label="t('evaluations.createEvaluation')"
                :title="t('evaluations.newEvaluation')"
                :description="t('evaluations.createDescription')"
                show-score-mode-selector
                @submit="submit"
                @cancel="goBack"
                @reload="reloadStudents"
            />
        </section>
    </AdminLayout>
</template>
