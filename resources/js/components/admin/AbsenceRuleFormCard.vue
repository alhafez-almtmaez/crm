<script setup>
import Button from 'primevue/button';
import Checkbox from 'primevue/checkbox';
import FloatLabel from 'primevue/floatlabel';
import Select from 'primevue/select';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import PrimeFloatField from '../form/PrimeFloatField.vue';

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
    templates: {
        type: Array,
        default: () => [],
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

const emit = defineEmits(['cancel', 'submit']);
const { t } = useI18n();

const attendanceTypeOptions = computed(() => [
    { value: 'absence', label: t('absenceRules.attendanceAbsence') },
    { value: 'excused_absence', label: t('absenceRules.attendanceExcusedAbsence') },
]);

const actionOptions = computed(() => [
    { value: 'freeze_student', label: t('absenceRules.actionFreezeStudent') },
    { value: 'dismiss_student', label: t('absenceRules.actionDismissStudent') },
]);

const filteredTemplates = computed(() => (
    (props.templates ?? []).map((template) => ({
        id: template.id,
        name: `${template.name} (${template.key})`,
    }))
));

const isFreezeAction = computed(() => props.form.action === 'freeze_student');
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
                            input-id="absence-rule-center"
                            v-model="props.form.center_id"
                            :options="props.centers"
                            option-label="name"
                            option-value="id"
                            filter
                            show-clear
                            class="h-11 w-full rounded-md border border-(--border) bg-(--background) text-(--foreground) shadow-none"
                        />
                        <label for="absence-rule-center">{{ t('absenceRules.center') }}</label>
                    </FloatLabel>
                    <small v-if="props.form.errors.center_id" class="text-sm text-red-600">{{ props.form.errors.center_id }}</small>
                    <small v-else class="text-xs text-(--muted-foreground)">{{ t('absenceRules.centerOptionalHint') }}</small>
                </div>

                <PrimeFloatField
                    id="absence-rule-occurrence"
                    v-model="props.form.occurrence_number"
                    :label="t('absenceRules.occurrenceNumber')"
                    input-type="number"
                    required
                    :invalid="Boolean(props.form.errors.occurrence_number)"
                    :error="props.form.errors.occurrence_number"
                />
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <div class="flex flex-col gap-1">
                    <FloatLabel variant="on">
                        <Select
                            input-id="absence-rule-attendance-type"
                            v-model="props.form.attendance_type"
                            :options="attendanceTypeOptions"
                            option-label="label"
                            option-value="value"
                            class="h-11 w-full rounded-md border border-(--border) bg-(--background) text-(--foreground) shadow-none"
                        />
                        <label for="absence-rule-attendance-type">{{ t('absenceRules.attendanceType') }}</label>
                    </FloatLabel>
                    <small v-if="props.form.errors.attendance_type" class="text-sm text-red-600">{{ props.form.errors.attendance_type }}</small>
                </div>

                <div class="flex flex-col gap-1">
                    <FloatLabel variant="on">
                        <Select
                            input-id="absence-rule-action"
                            v-model="props.form.action"
                            :options="actionOptions"
                            option-label="label"
                            option-value="value"
                            class="h-11 w-full rounded-md border border-(--border) bg-(--background) text-(--foreground) shadow-none"
                        />
                        <label for="absence-rule-action">{{ t('absenceRules.action') }}</label>
                    </FloatLabel>
                    <small v-if="props.form.errors.action" class="text-sm text-red-600">{{ props.form.errors.action }}</small>
                </div>
            </div>

            <div class="flex flex-col gap-1">
                <FloatLabel variant="on">
                    <Select
                        input-id="absence-rule-template"
                        v-model="props.form.message_template_id"
                        :options="filteredTemplates"
                        option-label="name"
                        option-value="id"
                        filter
                        show-clear
                        class="h-11 w-full rounded-md border border-(--border) bg-(--background) text-(--foreground) shadow-none"
                    />
                    <label for="absence-rule-template">{{ t('absenceRules.messageTemplate') }}</label>
                </FloatLabel>
                <small v-if="props.form.errors.message_template_id" class="text-sm text-red-600">{{ props.form.errors.message_template_id }}</small>
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <PrimeFloatField
                    id="absence-rule-deduction-points-count"
                    v-model="props.form.deduction_points_count"
                    :label="t('absenceRules.deductionPointsCount')"
                    input-type="number"
                    :invalid="Boolean(props.form.errors.deduction_points_count)"
                    :error="props.form.errors.deduction_points_count"
                />

                <PrimeFloatField
                    v-if="isFreezeAction"
                    id="absence-rule-freeze-working-days"
                    v-model="props.form.freeze_working_days_count"
                    :label="t('absenceRules.freezeWorkingDays')"
                    input-type="number"
                    :invalid="Boolean(props.form.errors.freeze_working_days_count)"
                    :error="props.form.errors.freeze_working_days_count"
                />
            </div>

            <PrimeFloatField
                v-if="isFreezeAction"
                id="absence-rule-freeze-reason"
                v-model="props.form.freeze_reason"
                :label="t('absenceRules.freezeReason')"
                autocomplete="off"
                :invalid="Boolean(props.form.errors.freeze_reason)"
                :error="props.form.errors.freeze_reason"
            />

            <div class="grid gap-2 md:grid-cols-2">
                <label class="flex items-center gap-2 text-sm">
                    <Checkbox v-model="props.form.send_to_center_group" binary input-id="absence-rule-send-to-group" />
                    <span>{{ t('absenceRules.sendToCenterGroup') }}</span>
                </label>
                <label class="flex items-center gap-2 text-sm">
                    <Checkbox v-model="props.form.is_active" binary input-id="absence-rule-active" />
                    <span>{{ t('absenceRules.isActive') }}</span>
                </label>
            </div>

            <small v-if="props.form.errors.send_to_center_group" class="text-sm text-red-600">{{ props.form.errors.send_to_center_group }}</small>
            <small v-if="props.form.errors.is_active" class="text-sm text-red-600">{{ props.form.errors.is_active }}</small>

            <div class="mt-2 flex justify-end gap-2">
                <Button type="button" :label="t('common.cancel')" severity="secondary" text @click="emit('cancel')" />
                <Button type="submit" :label="submitLabel" :loading="props.form.processing" />
            </div>
        </form>
    </article>
</template>
