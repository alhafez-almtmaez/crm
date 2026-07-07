<script setup>
import { computed, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import PrimeFloatField from '../form/PrimeFloatField.vue';

const dayOrder = [
    'sunday',
    'monday',
    'tuesday',
    'wednesday',
    'thursday',
    'friday',
    'saturday',
];

const props = defineProps({
    defaultLimit: {
        type: [Number, String],
        default: 2,
    },
    errors: {
        type: Object,
        default: () => ({}),
    },
    modelValue: {
        type: Object,
        default: () => ({}),
    },
    workingDays: {
        type: Array,
        default: () => [],
    },
});

const emit = defineEmits(['update:modelValue']);
const { t } = useI18n();

const normalizedDefaultLimit = computed(() => {
    const limit = Number(props.defaultLimit);

    return Number.isInteger(limit) && limit > 0 ? limit : 2;
});

const normalizedWorkingDays = computed(() => {
    const validDays = new Set(dayOrder);
    const selectedDays = props.workingDays
        .map((day) => String(day).toLowerCase())
        .filter((day, index, days) => validDays.has(day) && days.indexOf(day) === index);

    return dayOrder.filter((day) => selectedDays.includes(day));
});

const fieldError = (day) => props.errors[`daily_weight_limits.${day}`] ?? '';
const valueForDay = (day) => props.modelValue?.[day] ?? normalizedDefaultLimit.value;

const updateDayLimit = (day, value) => {
    emit('update:modelValue', {
        ...(props.modelValue ?? {}),
        [day]: value,
    });
};

watch(
    [normalizedWorkingDays, normalizedDefaultLimit],
    ([workingDays]) => {
        const nextLimits = {};

        for (const day of workingDays) {
            nextLimits[day] = props.modelValue?.[day] ?? normalizedDefaultLimit.value;
        }

        const currentKeys = Object.keys(props.modelValue ?? {}).sort();
        const nextKeys = Object.keys(nextLimits).sort();
        const changed = currentKeys.length !== nextKeys.length
            || nextKeys.some((day) => String(props.modelValue?.[day] ?? '') !== String(nextLimits[day] ?? ''));

        if (changed) {
            emit('update:modelValue', nextLimits);
        }
    },
    { immediate: true },
);
</script>

<template>
    <div v-if="normalizedWorkingDays.length" class="grid gap-3 md:col-span-2">
        <h3 class="text-sm font-semibold text-(--foreground)">
            {{ t('students.dailyWeightLimits') }}
        </h3>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <PrimeFloatField
                v-for="day in normalizedWorkingDays"
                :id="`student-daily-weight-${day}`"
                :key="day"
                :model-value="valueForDay(day)"
                :label="t(`days.${day}`)"
                input-type="number"
                :input-props="{ min: '1', max: '99', step: '1' }"
                required
                :invalid="Boolean(fieldError(day))"
                :error="fieldError(day)"
                @update:model-value="updateDayLimit(day, $event)"
            />
        </div>
    </div>
</template>
