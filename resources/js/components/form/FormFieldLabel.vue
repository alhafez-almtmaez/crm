<script setup>
import { useI18n } from 'vue-i18n';

defineProps({
    forId: {
        type: String,
        required: true,
    },
    text: {
        type: String,
        required: true,
    },
    required: {
        type: Boolean,
        default: false,
    },
});

const { t } = useI18n();
</script>

<template>
    <label :for="forId" class="inline-flex items-center">
        <span>{{ text }}</span>
        <span v-if="required" class="ms-1 font-bold text-red-600" aria-hidden="true">*</span>
        <span v-if="required" class="sr-only">({{ t('common.required') }})</span>
    </label>
</template>
