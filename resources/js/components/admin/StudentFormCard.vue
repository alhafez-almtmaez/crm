<script setup>
import axios from 'axios';
import Button from 'primevue/button';
import DatePicker from 'primevue/datepicker';
import FloatLabel from 'primevue/floatlabel';
import IntlTelInput from 'intl-tel-input/vueWithUtils';
import Select from 'primevue/select';
import { computed, ref, watch } from 'vue';
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
    centers: {
        type: Array,
        default: () => [],
    },
    admins: {
        type: Array,
        default: () => [],
    },
    canAssignAdmin: {
        type: Boolean,
        default: false,
    },
    plans: {
        type: Array,
        default: () => [],
    },
    initialGroups: {
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

const groupOptions = ref([...(props.initialGroups ?? [])]);
const groupsLoading = ref(false);
const parentPhoneValid = ref(null);
const parentPhoneErrorCode = ref(null);
const parentPhoneTouched = ref(false);
const phoneValid = ref(null);
const phoneErrorCode = ref(null);
const phoneTouched = ref(false);

const phoneOptions = {
    initialCountry: 'jo',
    preferredCountries: ['jo', 'sa', 'ae', 'eg'],
    allowDropdown: true,
    nationalMode: false,
    separateDialCode: true,
    strictMode: true,
};

const phoneInputClass = 'h-11 w-full rounded-md border border-(--border) bg-(--background) px-3 text-left text-(--foreground) shadow-none';
const parentPhoneInputProps = computed(() => ({
    id: 'student-parent-phone',
    class: phoneInputClass,
    autocomplete: 'tel',
    placeholder: t('students.phonePlaceholder'),
}));
const phoneInputProps = computed(() => ({
    id: 'student-phone',
    class: phoneInputClass,
    autocomplete: 'tel',
    placeholder: t('students.phonePlaceholder'),
}));

const statusOptions = computed(() => [
    { value: 1, label: t('students.statusActive') },
    { value: 0, label: t('students.statusInactive') },
    { value: 2, label: t('students.statusFrozen') },
]);
const maxBirthDate = new Date();

const dateOfBirthValue = computed({
    get: () => {
        const value = props.form.date_of_birth;

        if (!value || typeof value !== 'string') {
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
            props.form.date_of_birth = '';
            return;
        }

        const year = value.getFullYear();
        const month = String(value.getMonth() + 1).padStart(2, '0');
        const day = String(value.getDate()).padStart(2, '0');
        props.form.date_of_birth = `${year}-${month}-${day}`;
    },
});

const mapPhoneError = (errorCode) => {
    const code = errorCode ?? 0;
    const errorMap = {
        1: t('students.phoneErrorInvalidCountryCode'),
        2: t('students.phoneErrorTooShort'),
        3: t('students.phoneErrorTooLong'),
        4: t('students.phoneErrorInvalid'),
        5: t('students.phoneErrorInvalidLength'),
    };

    return errorMap[code] ?? t('students.phoneErrorInvalid');
};

const parentPhoneErrorMessage = computed(() => {
    if (typeof props.form.errors.parent_phone_number === 'string' && props.form.errors.parent_phone_number !== '') {
        return props.form.errors.parent_phone_number;
    }

    if (!parentPhoneTouched.value && !phoneTouched.value) {
        return '';
    }

    if (!props.form.parent_phone_number && !props.form.phone_number) {
        return t('students.phoneOneRequired');
    }

    if (props.form.parent_phone_number && parentPhoneValid.value === false) {
        return mapPhoneError(parentPhoneErrorCode.value);
    }

    return '';
});

const phoneErrorMessage = computed(() => {
    if (typeof props.form.errors.phone_number === 'string' && props.form.errors.phone_number !== '') {
        return props.form.errors.phone_number;
    }

    if (!parentPhoneTouched.value && !phoneTouched.value) {
        return '';
    }

    if (!props.form.parent_phone_number && !props.form.phone_number) {
        return t('students.phoneOneRequired');
    }

    if (props.form.phone_number && phoneValid.value === false) {
        return mapPhoneError(phoneErrorCode.value);
    }

    return '';
});

const onParentPhoneChange = (value) => {
    parentPhoneTouched.value = true;
    props.form.parent_phone_number = value ?? '';
};

const onPhoneChange = (value) => {
    phoneTouched.value = true;
    props.form.phone_number = value ?? '';
};

const onSubmit = () => {
    parentPhoneTouched.value = true;
    phoneTouched.value = true;

    if (parentPhoneErrorMessage.value !== '' || phoneErrorMessage.value !== '') {
        return;
    }

    emit('submit');
};

let groupsRequestToken = 0;

watch(
    () => props.form.center_id,
    async (centerId) => {
        const nextCenterId = centerId ? Number(centerId) : null;
        groupsRequestToken += 1;
        const currentToken = groupsRequestToken;

        if (!nextCenterId) {
            groupOptions.value = [];
            props.form.group_id = null;
            return;
        }

        groupsLoading.value = true;

        try {
            const { data } = await axios.get(`/admin/centers/${nextCenterId}/groups`);

            if (currentToken !== groupsRequestToken) {
                return;
            }

            groupOptions.value = data?.data ?? [];
            const hasCurrentGroup = groupOptions.value.some((group) => group.id === props.form.group_id);

            if (!hasCurrentGroup) {
                props.form.group_id = null;
            }
        } catch {
            if (currentToken !== groupsRequestToken) {
                return;
            }

            groupOptions.value = [];
            props.form.group_id = null;
        } finally {
            if (currentToken === groupsRequestToken) {
                groupsLoading.value = false;
            }
        }
    },
    { immediate: true },
);
</script>

<template>
    <article class="rounded-(--radius-base) border border-(--border) bg-(--card) p-6 text-(--card-foreground) shadow-(--shadow-sm) sm:p-8">
        <h2 v-if="title" class="text-2xl font-semibold">{{ title }}</h2>
        <p v-if="description" class="mt-3 text-lg text-(--muted-foreground)">{{ description }}</p>

        <form class="mt-6 grid gap-4" @submit.prevent="onSubmit">
            <div class="grid gap-4 md:grid-cols-2">
                <PrimeFloatField
                    id="student-first-name"
                    v-model="form.first_name"
                    :label="t('students.firstName')"
                    autocomplete="off"
                    required
                    :invalid="Boolean(form.errors.first_name)"
                    :error="form.errors.first_name"
                />

                <PrimeFloatField
                    id="student-second-name"
                    v-model="form.second_name"
                    :label="t('students.secondName')"
                    autocomplete="off"
                    required
                    :invalid="Boolean(form.errors.second_name)"
                    :error="form.errors.second_name"
                />

                <PrimeFloatField
                    id="student-middle-name"
                    v-model="form.middle_name"
                    :label="t('students.middleName')"
                    autocomplete="off"
                    required
                    :invalid="Boolean(form.errors.middle_name)"
                    :error="form.errors.middle_name"
                />

                <PrimeFloatField
                    id="student-last-name"
                    v-model="form.last_name"
                    :label="t('students.lastName')"
                    autocomplete="off"
                    required
                    :invalid="Boolean(form.errors.last_name)"
                    :error="form.errors.last_name"
                />
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <PrimeFloatField
                    id="student-id-number"
                    v-model="form.id_number"
                    :label="t('students.idNumber')"
                    autocomplete="off"
                    :invalid="Boolean(form.errors.id_number)"
                    :error="form.errors.id_number"
                />

                <PrimeFloatField
                    id="student-email"
                    v-model="form.email"
                    :label="t('auth.email')"
                    input-type="email"
                    autocomplete="off"
                    :invalid="Boolean(form.errors.email)"
                    :error="form.errors.email"
                />

                <div class="flex flex-col gap-1">
                    <FloatLabel variant="on">
                        <DatePicker
                            input-id="student-date-of-birth"
                            v-model="dateOfBirthValue"
                            show-icon
                            icon-display="input"
                            date-format="yy-mm-dd"
                            :max-date="maxBirthDate"
                            :manual-input="false"
                            class="h-11 w-full rounded-md border border-(--border) bg-(--background) text-(--foreground) shadow-none"
                        />
                        <FormFieldLabel for-id="student-date-of-birth" :text="t('students.dateOfBirth')" />
                    </FloatLabel>
                    <small v-if="form.errors.date_of_birth" class="text-sm text-red-600">{{ form.errors.date_of_birth }}</small>
                </div>
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <div class="flex flex-col gap-1">
                    <label for="student-parent-phone" class="text-sm font-medium">
                        {{ t('students.parentPhone') }}
                    </label>
                    <IntlTelInput
                        v-model="form.parent_phone_number"
                        :options="phoneOptions"
                        :input-props="parentPhoneInputProps"
                        @change-number="onParentPhoneChange"
                        @change-validity="parentPhoneValid = $event"
                        @change-error-code="parentPhoneErrorCode = $event"
                    />
                    <small v-if="parentPhoneErrorMessage" class="text-sm text-red-600">{{ parentPhoneErrorMessage }}</small>
                </div>

                <div class="flex flex-col gap-1">
                    <label for="student-phone" class="text-sm font-medium">
                        {{ t('students.phone') }}
                    </label>
                    <IntlTelInput
                        v-model="form.phone_number"
                        :options="phoneOptions"
                        :input-props="phoneInputProps"
                        @change-number="onPhoneChange"
                        @change-validity="phoneValid = $event"
                        @change-error-code="phoneErrorCode = $event"
                    />
                    <small v-if="phoneErrorMessage" class="text-sm text-red-600">{{ phoneErrorMessage }}</small>
                </div>
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <div class="flex flex-col gap-1">
                    <FloatLabel variant="on">
                        <Select
                            input-id="student-center-id"
                            v-model="form.center_id"
                            :options="centers"
                            option-label="name"
                            option-value="id"
                            filter
                            class="h-11 w-full rounded-md border border-(--border) bg-(--background) text-(--foreground) shadow-none"
                        />
                        <FormFieldLabel for-id="student-center-id" :text="t('groups.center')" required />
                    </FloatLabel>
                    <small v-if="form.errors.center_id" class="text-sm text-red-600">{{ form.errors.center_id }}</small>
                </div>

                <div class="flex flex-col gap-1">
                    <FloatLabel variant="on">
                        <Select
                            input-id="student-group-id"
                            v-model="form.group_id"
                            :options="groupOptions"
                            option-label="name"
                            option-value="id"
                            filter
                            show-clear
                            :loading="groupsLoading"
                            :disabled="!form.center_id"
                            class="h-11 w-full rounded-md border border-(--border) bg-(--background) text-(--foreground) shadow-none"
                        />
                        <FormFieldLabel for-id="student-group-id" :text="t('students.group')" />
                    </FloatLabel>
                    <small v-if="form.errors.group_id" class="text-sm text-red-600">{{ form.errors.group_id }}</small>
                    <small v-else-if="form.center_id && !groupsLoading && groupOptions.length === 0" class="text-xs text-(--muted-foreground)">
                        {{ t('students.noGroupsForCenter') }}
                    </small>
                </div>

                <div class="flex flex-col gap-1">
                    <FloatLabel variant="on">
                        <Select
                            input-id="student-plan-id"
                            v-model="form.plan_type_id"
                            :options="plans"
                            option-label="name"
                            option-value="id"
                            filter
                            class="h-11 w-full rounded-md border border-(--border) bg-(--background) text-(--foreground) shadow-none"
                        />
                        <FormFieldLabel for-id="student-plan-id" :text="t('students.plan')" required />
                    </FloatLabel>
                    <small v-if="form.errors.plan_type_id" class="text-sm text-red-600">{{ form.errors.plan_type_id }}</small>
                </div>

                <div v-if="canAssignAdmin" class="flex flex-col gap-1">
                    <FloatLabel variant="on">
                        <Select
                            input-id="student-admin-id"
                            v-model="form.admin_id"
                            :options="admins"
                            option-label="name"
                            option-value="id"
                            filter
                            class="h-11 w-full rounded-md border border-(--border) bg-(--background) text-(--foreground) shadow-none"
                        />
                        <FormFieldLabel for-id="student-admin-id" :text="t('students.admin')" required />
                    </FloatLabel>
                    <small v-if="form.errors.admin_id" class="text-sm text-red-600">{{ form.errors.admin_id }}</small>
                </div>

                <div class="flex flex-col gap-1">
                    <FloatLabel variant="on">
                        <Select
                            input-id="student-status"
                            v-model="form.is_active"
                            :options="statusOptions"
                            option-label="label"
                            option-value="value"
                            class="h-11 w-full rounded-md border border-(--border) bg-(--background) text-(--foreground) shadow-none"
                        />
                        <FormFieldLabel for-id="student-status" :text="t('students.status')" required />
                    </FloatLabel>
                    <small v-if="form.errors.is_active" class="text-sm text-red-600">{{ form.errors.is_active }}</small>
                </div>
            </div>

            <div class="mt-2 flex justify-end gap-2">
                <Button type="button" :label="t('common.cancel')" severity="secondary" text @click="emit('cancel')" />
                <Button type="submit" :label="submitLabel" :loading="form.processing" />
            </div>
        </form>
    </article>
</template>
