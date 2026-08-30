<script setup>
import Button from 'primevue/button';
import IntlTelInput from 'intl-tel-input/vueWithUtils';
import SelectButton from 'primevue/selectbutton';
import ToggleSwitch from 'primevue/toggleswitch';
import { computed, ref } from 'vue';
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

const studentGenderOptions = computed(() => [
    { value: 'male', label: t('centers.maleStudents'), icon: 'pi pi-mars' },
    { value: 'female', label: t('centers.femaleStudents'), icon: 'pi pi-venus' },
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
                <div class="rounded-(--radius-base) border border-(--border) bg-(--background) p-4 sm:p-5">
                    <div class="mb-4 flex items-start gap-3">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-(--muted) text-(--foreground)">
                            <i class="pi pi-users text-lg" aria-hidden="true"></i>
                        </span>
                        <div class="min-w-0">
                            <p id="center-student-gender-label" class="font-semibold text-(--foreground)">
                                {{ t('centers.studentType') }}
                                <span class="text-red-600">*</span>
                            </p>
                            <p class="mt-1 text-sm leading-6 text-(--muted-foreground)">
                                {{ t('centers.studentTypeHint') }}
                            </p>
                        </div>
                    </div>

                    <SelectButton
                        v-model="props.form.student_gender"
                        :options="studentGenderOptions"
                        option-label="label"
                        option-value="value"
                        :allow-empty="false"
                        :invalid="Boolean(props.form.errors.student_gender)"
                        aria-labelledby="center-student-gender-label"
                        fluid
                    >
                        <template #option="{ option }">
                            <span class="flex items-center justify-center gap-2 py-1 font-semibold">
                                <i :class="option.icon" aria-hidden="true"></i>
                                <span>{{ option.label }}</span>
                            </span>
                        </template>
                    </SelectButton>
                </div>
                <small v-if="props.form.errors.student_gender" class="text-sm text-red-600">
                    {{ props.form.errors.student_gender }}
                </small>
            </div>

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

            <section class="mt-2 overflow-hidden rounded-(--radius-base) border border-(--border) bg-(--background)">
                <div class="flex items-start gap-3 border-b border-(--border) bg-(--muted) px-4 py-4 sm:px-5">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-(--primary) text-(--primary-foreground) shadow-sm">
                        <i class="pi pi-file-check text-lg" aria-hidden="true"></i>
                    </span>
                    <div class="min-w-0">
                        <h3 class="font-semibold text-(--foreground)">{{ t('centers.certificateSettings') }}</h3>
                        <p class="mt-1 text-sm leading-6 text-(--muted-foreground)">{{ t('centers.certificateSettingsDescription') }}</p>
                    </div>
                </div>

                <div class="grid gap-4 p-4 sm:p-5 md:grid-cols-2">
                    <PrimeFloatField
                        id="center-certificate-name"
                        v-model="props.form.certificate_name"
                        :label="t('centers.certificateName')"
                        autocomplete="off"
                        :invalid="Boolean(props.form.errors.certificate_name)"
                        :error="props.form.errors.certificate_name"
                        :hint="t('centers.certificateNameHint')"
                    />

                    <div class="flex flex-col gap-1">
                        <div
                            class="flex min-h-20 items-center justify-between gap-4 rounded-md border px-4 py-3 transition-colors"
                            :class="props.form.show_center_manager_signature
                                ? 'border-emerald-300 bg-emerald-50/70 dark:border-emerald-800 dark:bg-emerald-950/20'
                                : 'border-(--border) bg-(--card)'"
                        >
                            <div class="min-w-0">
                                <label for="center-manager-signature" class="cursor-pointer text-sm font-semibold text-(--foreground)">
                                    {{ t('centers.showCenterManagerSignature') }}
                                </label>
                                <p id="center-manager-signature-hint" class="mt-1 text-xs leading-5 text-(--muted-foreground)">
                                    {{ t('centers.centerManagerSignatureHint') }}
                                </p>
                            </div>

                            <div class="flex shrink-0 flex-col items-center gap-1.5">
                                <ToggleSwitch
                                    input-id="center-manager-signature"
                                    v-model="props.form.show_center_manager_signature"
                                    aria-describedby="center-manager-signature-hint"
                                />
                                <span
                                    class="inline-flex min-w-12 items-center justify-center rounded-full px-2 py-0.5 text-[11px] font-semibold"
                                    :class="props.form.show_center_manager_signature
                                        ? 'bg-(--primary) text-(--primary-foreground) shadow-sm'
                                        : 'bg-(--muted) text-(--muted-foreground)'"
                                >
                                    {{ props.form.show_center_manager_signature ? t('common.active') : t('common.inactive') }}
                                </span>
                            </div>
                        </div>
                        <small v-if="props.form.errors.show_center_manager_signature" class="text-sm text-red-600">
                            {{ props.form.errors.show_center_manager_signature }}
                        </small>
                    </div>
                </div>
            </section>

            <div class="mt-2 flex justify-end gap-2">
                <Button type="button" :label="t('common.cancel')" severity="secondary" text @click="emit('cancel')" />
                <Button type="submit" :label="submitLabel" :loading="props.form.processing" />
            </div>
        </form>
    </article>
</template>
