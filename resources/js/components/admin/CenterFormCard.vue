<script setup>
import Button from 'primevue/button';
import FloatLabel from 'primevue/floatlabel';
import IntlTelInput from 'intl-tel-input/vueWithUtils';
import MultiSelect from 'primevue/multiselect';
import Select from 'primevue/select';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import FormFieldLabel from '../form/FormFieldLabel.vue';
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
    whatsappGroups: {
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
const phoneValid = ref(null);
const phoneErrorCode = ref(null);
const phoneTouched = ref(false);

const phoneInputProps = computed(() => ({
    id: 'center-phone',
    class: 'h-11 w-full rounded-md border border-(--border) bg-(--background) px-3 text-(--foreground) shadow-none',
    autocomplete: 'tel',
    placeholder: t('centers.phonePlaceholder'),
}));

const phoneOptions = {
    initialCountry: 'jo',
    onlyCountries: ['jo'],
    allowDropdown: false,
    nationalMode: false,
    strictMode: true,
};

const dayOptions = computed(() => [
    { value: 'sunday', label: t('days.sunday') },
    { value: 'monday', label: t('days.monday') },
    { value: 'tuesday', label: t('days.tuesday') },
    { value: 'wednesday', label: t('days.wednesday') },
    { value: 'thursday', label: t('days.thursday') },
    { value: 'friday', label: t('days.friday') },
    { value: 'saturday', label: t('days.saturday') },
]);

const phoneErrorMessage = computed(() => {
    if (typeof props.form.errors.phone === 'string' && props.form.errors.phone !== '') {
        return props.form.errors.phone;
    }

    if (!phoneTouched.value) {
        return '';
    }

    if (!props.form.phone) {
        return t('centers.phoneRequired');
    }

    if (phoneValid.value !== false) {
        return '';
    }

    const code = phoneErrorCode.value ?? 0;
    const errorMap = {
        1: t('centers.phoneErrorInvalidCountryCode'),
        2: t('centers.phoneErrorTooShort'),
        3: t('centers.phoneErrorTooLong'),
        4: t('centers.phoneErrorInvalid'),
        5: t('centers.phoneErrorInvalidLength'),
    };

    return errorMap[code] ?? t('centers.phoneErrorInvalid');
});

const onPhoneNumberChange = (value) => {
    phoneTouched.value = true;
    props.form.phone = value ?? '';
};

const onPhoneValidityChange = (value) => {
    phoneValid.value = value;
};

const onPhoneErrorCodeChange = (value) => {
    phoneErrorCode.value = value;
};

const onSubmit = () => {
    phoneTouched.value = true;

    if (phoneErrorMessage.value !== '') {
        return;
    }

    emit('submit');
};
</script>

<template>
    <article class="rounded-(--radius-base) border border-(--border) bg-(--card) p-6 text-(--card-foreground) shadow-(--shadow-sm) sm:p-8">
        <h2 v-if="title" class="text-2xl font-semibold">{{ title }}</h2>
        <p v-if="description" class="mt-3 text-lg text-(--muted-foreground)">{{ description }}</p>

        <form class="mt-6 grid gap-4" @submit.prevent="onSubmit">
            <PrimeFloatField
                id="center-name"
                v-model="props.form.name"
                :label="t('centers.centerName')"
                autocomplete="off"
                required
                :invalid="Boolean(props.form.errors.name)"
                :error="props.form.errors.name"
            />

            <div class="flex flex-col gap-1">
                <label for="center-phone" class="text-sm font-medium">
                    {{ t('centers.phone') }}
                    <span class="text-red-600">*</span>
                </label>
                <IntlTelInput
                    v-model="props.form.phone"
                    :options="phoneOptions"
                    :input-props="phoneInputProps"
                    @change-number="onPhoneNumberChange"
                    @change-validity="onPhoneValidityChange"
                    @change-error-code="onPhoneErrorCodeChange"
                />
                <small v-if="phoneErrorMessage" class="text-sm text-red-600">{{ phoneErrorMessage }}</small>
            </div>

            <div class="flex flex-col gap-1">
                <FloatLabel variant="on">
                    <Select
                        input-id="center-group-serialized"
                        v-model="props.form.group_serialized"
                        :options="props.whatsappGroups"
                        option-label="label"
                        option-value="value"
                        filter
                        show-clear
                        class="h-11 w-full rounded-md border border-(--border) bg-(--background) text-(--foreground) shadow-none"
                    />
                    <FormFieldLabel for-id="center-group-serialized" :text="t('centers.whatsappGroup')" />
                </FloatLabel>
                <small v-if="props.form.errors.group_serialized" class="text-sm text-red-600">{{ props.form.errors.group_serialized }}</small>
                <small v-if="props.whatsappGroups.length === 0" class="text-xs text-(--muted-foreground)">{{ t('centers.noWhatsappGroups') }}</small>
            </div>

            <div class="flex flex-col gap-1">
                <FloatLabel variant="on">
                    <MultiSelect
                        input-id="center-working-days"
                        v-model="props.form.working_days"
                        :options="dayOptions"
                        option-label="label"
                        option-value="value"
                        display="chip"
                        :max-selected-labels="4"
                        :selected-items-label="t('common.selectedCount')"
                        class="h-11 w-full rounded-md border border-(--border) bg-(--background) text-(--foreground) shadow-none"
                    />
                    <FormFieldLabel for-id="center-working-days" :text="t('centers.workingDays')" required />
                </FloatLabel>
                <small v-if="props.form.errors.working_days" class="text-sm text-red-600">{{ props.form.errors.working_days }}</small>
                <small v-if="props.form.errors['working_days.0']" class="text-sm text-red-600">{{ props.form.errors['working_days.0'] }}</small>
            </div>

            <div class="mt-2 flex justify-end gap-2">
                <Button type="button" :label="t('common.cancel')" severity="secondary" text @click="emit('cancel')" />
                <Button type="submit" :label="submitLabel" :loading="props.form.processing" />
            </div>
        </form>
    </article>
</template>
