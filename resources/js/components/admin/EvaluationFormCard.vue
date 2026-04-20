<script setup>
import Button from 'primevue/button';
import DatePicker from 'primevue/datepicker';
import FloatLabel from 'primevue/floatlabel';
import Select from 'primevue/select';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import FormFieldLabel from '../form/FormFieldLabel.vue';

const props = defineProps({
    description: {
        type: String,
        default: '',
    },
    form: {
        type: Object,
        required: true,
    },
    centers: {
        type: Array,
        default: () => [],
    },
    lockCenterAndDate: {
        type: Boolean,
        default: false,
    },
    highlightUnchanged: {
        type: Boolean,
        default: false,
    },
    showScoreModeSelector: {
        type: Boolean,
        default: false,
    },
    submitLabel: {
        type: String,
        default: 'Save',
    },
    title: {
        type: String,
        default: '',
    },
});

const emit = defineEmits(['cancel', 'reload', 'submit']);
const { t } = useI18n();

const attendanceOptions = computed(() => [
    { value: 1, label: t('evaluations.present') },
    { value: 2, label: t('evaluations.excusedAbsence') },
    { value: 3, label: t('evaluations.absence') },
]);
const scoreMode = ref('alhifz');
const scoreModeOptions = computed(() => [
    { value: 'alhifz', label: t('evaluations.alhifz') },
    { value: 'tajwid', label: t('evaluations.tajwid') },
]);
const visiblePrimaryScoreField = computed(() => (scoreMode.value === 'tajwid' ? 'tajwid' : 'alhifz'));
const visiblePrimaryScoreLabel = computed(() => (
    visiblePrimaryScoreField.value === 'tajwid'
        ? t('evaluations.tajwid')
        : t('evaluations.alhifz')
));

const dateValue = computed({
    get: () => {
        const value = props.form.date;
        if (typeof value !== 'string' || value === '') {
            return null;
        }

        const parts = value.split('-').map((segment) => Number(segment));
        if (parts.length !== 3 || parts.some((part) => Number.isNaN(part))) {
            return null;
        }

        return new Date(parts[0], parts[1] - 1, parts[2]);
    },
    set: (value) => {
        if (!(value instanceof Date) || Number.isNaN(value.getTime())) {
            props.form.date = '';
            return;
        }

        const year = value.getFullYear();
        const month = String(value.getMonth() + 1).padStart(2, '0');
        const day = String(value.getDate()).padStart(2, '0');
        props.form.date = `${year}-${month}-${day}`;
    },
});

const onAttendanceChange = (item) => {
    if (!item) {
        return;
    }

    if (Number(item.attendances) === 1) {
        if (item.alhifz === null || item.alhifz === '') {
            item.alhifz = 10;
        }
        if (item.warud === null || item.warud === '') {
            item.warud = 10;
        }
        if (item.akhlaqi === null || item.akhlaqi === '') {
            item.akhlaqi = 10;
        }
        if (item.tajwid === null || item.tajwid === '') {
            item.tajwid = 10;
        }
        return;
    }

    item.alhifz = null;
    item.warud = null;
    item.akhlaqi = null;
    item.tajwid = null;
};

const clampScoreValue = (value) => {
    if (value === null || value === '') {
        return null;
    }

    const parsed = Number(value);
    if (Number.isNaN(parsed)) {
        return null;
    }

    return Math.min(10, Math.max(0, parsed));
};

const clampScoreField = (item, field) => {
    if (!item) {
        return;
    }

    item[field] = clampScoreValue(item[field]);
};

const normalizeNumber = (value) => {
    if (value === null || value === '') {
        return null;
    }

    const number = Number(value);

    return Number.isNaN(number) ? null : number;
};

const normalizeText = (value) => {
    if (typeof value !== 'string') {
        return '';
    }

    return value.trim();
};

const isRowDirty = (item) => {
    if (!item || !item._baseline) {
        return false;
    }

    const baseline = item._baseline;

    return (
        normalizeNumber(item.attendances) !== normalizeNumber(baseline.attendances)
        || normalizeNumber(item.alhifz) !== normalizeNumber(baseline.alhifz)
        || normalizeNumber(item.warud) !== normalizeNumber(baseline.warud)
        || normalizeNumber(item.akhlaqi) !== normalizeNumber(baseline.akhlaqi)
        || normalizeNumber(item.tajwid) !== normalizeNumber(baseline.tajwid)
        || normalizeText(item.note) !== normalizeText(baseline.note)
    );
};

const rowToneClass = (item) => {
    if (isRowDirty(item)) {
        return 'bg-emerald-50/70 dark:bg-emerald-900/15';
    }

    if (props.highlightUnchanged && item?.is_default_entry && !item?.was_edited) {
        return 'bg-amber-50/70 dark:bg-amber-900/15';
    }

    return '';
};

const rowMarkerClass = (item) => {
    if (isRowDirty(item)) {
        return 'bg-emerald-500 shadow-[0_0_14px_rgba(16,185,129,0.8)]';
    }

    if (props.highlightUnchanged && item?.is_default_entry && !item?.was_edited) {
        return 'bg-amber-500 shadow-[0_0_14px_rgba(245,158,11,0.6)]';
    }

    return 'bg-transparent shadow-none';
};
</script>

<template>
    <article class="rounded-(--radius-base) border border-(--border) bg-(--card) p-6 text-(--card-foreground) shadow-(--shadow-sm) sm:p-8">
        <h2 v-if="title" class="text-2xl font-semibold">{{ title }}</h2>
        <p v-if="description" class="mt-3 text-lg text-(--muted-foreground)">{{ description }}</p>

        <form class="mt-6 grid gap-4" @submit.prevent="emit('submit')">
            <div class="grid gap-4 md:grid-cols-2">
                <div class="flex flex-col gap-1">
                    <FloatLabel variant="on">
                        <Select
                            input-id="evaluation-center"
                            v-model="form.center_id"
                            :options="centers"
                            option-label="name"
                            option-value="id"
                            filter
                            class="h-11 w-full rounded-md border border-(--border) bg-(--background) text-(--foreground) shadow-none"
                            :disabled="lockCenterAndDate"
                        />
                        <FormFieldLabel for-id="evaluation-center" :text="t('evaluations.center')" />
                    </FloatLabel>
                    <small v-if="form.errors.center_id" class="text-sm text-red-600">{{ form.errors.center_id }}</small>
                </div>

                <div class="flex flex-col gap-1">
                    <FloatLabel variant="on">
                        <DatePicker
                            input-id="evaluation-date"
                            v-model="dateValue"
                            show-icon
                            icon-display="input"
                            date-format="yy-mm-dd"
                            :manual-input="false"
                            class="h-11 w-full rounded-md border border-(--border) bg-(--background) text-(--foreground) shadow-none"
                            :disabled="lockCenterAndDate"
                        />
                        <FormFieldLabel for-id="evaluation-date" :text="t('evaluations.date')" />
                    </FloatLabel>
                    <small v-if="form.errors.date" class="text-sm text-red-600">{{ form.errors.date }}</small>
                </div>
            </div>

            <div v-if="showScoreModeSelector || !lockCenterAndDate" class="flex flex-wrap items-center justify-between gap-3">
                <div v-if="showScoreModeSelector" class="w-52">
                    <FloatLabel variant="on">
                        <Select
                            input-id="evaluation-score-mode"
                            v-model="scoreMode"
                            :options="scoreModeOptions"
                            option-label="label"
                            option-value="value"
                            class="h-11 w-full rounded-md border border-(--border) bg-(--background) text-(--foreground) shadow-none"
                        />
                        <FormFieldLabel for-id="evaluation-score-mode" :text="t('evaluations.scoreFieldMode')" />
                    </FloatLabel>
                </div>

                <Button
                    v-if="!lockCenterAndDate"
                    type="button"
                    icon="pi pi-refresh"
                    :label="t('evaluations.loadStudents')"
                    severity="secondary"
                    :disabled="!form.center_id || !form.date"
                    @click="emit('reload')"
                />
            </div>

            <div class="rounded-md border border-(--border)">
                <div class="border-b border-(--border) px-4 py-3">
                    <h3 class="text-lg font-semibold">{{ t('evaluations.studentsList') }}</h3>
                    <p class="text-sm text-(--muted-foreground)">{{ t('evaluations.studentsHint') }}</p>
                </div>

                <div v-if="form.items.length === 0" class="px-4 py-6 text-sm text-(--muted-foreground)">
                    {{ t('evaluations.noStudentsLoaded') }}
                </div>

                <div v-else class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-(--border)">
                        <thead>
                            <tr class="text-sm">
                                <th class="px-3 py-2 text-start font-semibold">{{ t('evaluations.student') }}</th>
                                <th class="px-3 py-2 text-start font-semibold">{{ t('evaluations.attendance') }}</th>
                                <th class="px-3 py-2 text-start font-semibold">
                                    {{ showScoreModeSelector ? visiblePrimaryScoreLabel : t('evaluations.alhifz') }}
                                </th>
                                <th class="px-3 py-2 text-start font-semibold">{{ t('evaluations.warud') }}</th>
                                <th class="px-3 py-2 text-start font-semibold">{{ t('evaluations.akhlaqi') }}</th>
                                <th
                                    v-if="!showScoreModeSelector"
                                    class="px-3 py-2 text-start font-semibold"
                                >
                                    {{ t('evaluations.tajwid') }}
                                </th>
                                <th class="px-3 py-2 text-start font-semibold">{{ t('evaluations.note') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-(--border)">
                            <tr
                                v-for="(item, index) in form.items"
                                :key="item.student_id"
                                :class="rowToneClass(item)"
                            >
                                <td class="relative px-3 py-2 align-top">
                                    <span
                                        class="pointer-events-none absolute start-0 top-2 bottom-2 w-1 rounded-full transition-all"
                                        :class="rowMarkerClass(item)"
                                    />
                                    <p class="font-semibold">{{ item.full_name }}</p>
                                    <p class="text-xs text-(--muted-foreground)">
                                        {{ item.plan_name || t('common.na') }}
                                        <span v-if="item.group_name">/ {{ item.group_name }}</span>
                                    </p>
                                </td>
                                <td class="px-3 py-2 align-top">
                                    <Select
                                        v-model.number="item.attendances"
                                        :options="attendanceOptions"
                                        option-label="label"
                                        option-value="value"
                                        class="w-44"
                                        @update:model-value="onAttendanceChange(item)"
                                    />
                                    <small
                                        v-if="form.errors[`items.${index}.attendances`]"
                                        class="text-xs text-red-600"
                                    >
                                        {{ form.errors[`items.${index}.attendances`] }}
                                    </small>
                                </td>
                                <td class="px-3 py-2 align-top">
                                    <input
                                        v-model.number="item[showScoreModeSelector ? visiblePrimaryScoreField : 'alhifz']"
                                        type="number"
                                        min="0"
                                        max="10"
                                        step="1"
                                        class="h-10 w-20 rounded-md border border-(--border) bg-(--background) px-2"
                                        :disabled="Number(item.attendances) !== 1"
                                        @input="clampScoreField(item, showScoreModeSelector ? visiblePrimaryScoreField : 'alhifz')"
                                    >
                                </td>
                                <td class="px-3 py-2 align-top">
                                    <input
                                        v-model.number="item.warud"
                                        type="number"
                                        min="0"
                                        max="10"
                                        step="1"
                                        class="h-10 w-20 rounded-md border border-(--border) bg-(--background) px-2"
                                        :disabled="Number(item.attendances) !== 1"
                                        @input="clampScoreField(item, 'warud')"
                                    >
                                </td>
                                <td class="px-3 py-2 align-top">
                                    <input
                                        v-model.number="item.akhlaqi"
                                        type="number"
                                        min="0"
                                        max="10"
                                        step="1"
                                        class="h-10 w-20 rounded-md border border-(--border) bg-(--background) px-2"
                                        :disabled="Number(item.attendances) !== 1"
                                        @input="clampScoreField(item, 'akhlaqi')"
                                    >
                                </td>
                                <td v-if="!showScoreModeSelector" class="px-3 py-2 align-top">
                                    <input
                                        v-model.number="item.tajwid"
                                        type="number"
                                        min="0"
                                        max="10"
                                        step="1"
                                        class="h-10 w-20 rounded-md border border-(--border) bg-(--background) px-2"
                                        :disabled="Number(item.attendances) !== 1"
                                        @input="clampScoreField(item, 'tajwid')"
                                    >
                                </td>
                                <td class="px-3 py-2 align-top">
                                    <input
                                        v-model="item.note"
                                        type="text"
                                        class="h-10 w-52 rounded-md border border-(--border) bg-(--background) px-2"
                                    >
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="mt-2 flex justify-end gap-2">
                <Button type="button" :label="t('common.cancel')" severity="secondary" text @click="emit('cancel')" />
                <Button type="submit" :label="submitLabel" :loading="form.processing" />
            </div>
        </form>
    </article>
</template>
