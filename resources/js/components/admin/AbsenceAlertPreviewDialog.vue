<script setup>
import Button from 'primevue/button';
import Dialog from 'primevue/dialog';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    modelValue: {
        type: Boolean,
        default: false,
    },
    messages: {
        type: Array,
        default: () => [],
    },
    loading: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['update:modelValue']);
const { t } = useI18n();

const visible = computed({
    get: () => props.modelValue,
    set: (value) => emit('update:modelValue', value),
});

const recipientText = (message) => {
    const phones = Array.isArray(message.recipient_phones) ? message.recipient_phones : [];
    const recipients = [...phones];

    if (message.sent_to_group) {
        recipients.push(message.group_serialized || t('evaluations.preview.centerGroup'));
    }

    return recipients.length > 0 ? recipients.join(', ') : t('common.na');
};
</script>

<template>
    <Dialog
        v-model:visible="visible"
        modal
        dismissable-mask
        :header="t('evaluations.preview.title')"
        :style="{ width: 'min(1040px, 96vw)' }"
    >
        <div class="space-y-4">
            <div class="rounded-md border border-amber-300 bg-amber-50 px-4 py-3 text-sm leading-6 text-amber-950">
                <strong class="font-semibold">{{ t('evaluations.preview.localOnlyTitle') }}</strong>
                <span class="ms-1">{{ t('evaluations.preview.localOnlyDescription') }}</span>
            </div>

            <div v-if="loading" class="py-12 text-center text-sm text-(--muted-foreground)">
                {{ t('common.loading') }}
            </div>

            <div v-else-if="messages.length === 0" class="py-12 text-center text-sm text-(--muted-foreground)">
                {{ t('evaluations.preview.empty') }}
            </div>

            <div v-else class="grid gap-3">
                <article
                    v-for="message in messages"
                    :key="message.id"
                    class="rounded-md border border-(--border) bg-(--card) p-4 text-(--card-foreground)"
                >
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div class="min-w-0">
                            <h3 class="text-base font-semibold leading-6">{{ message.student_name }}</h3>
                            <p class="mt-1 text-sm leading-6 text-(--muted-foreground)">
                                {{ message.center_name || t('common.na') }}
                            </p>
                        </div>

                        <span class="inline-flex w-fit items-center rounded-full bg-[color-mix(in_oklab,var(--accent)_14%,transparent)] px-2.5 py-1 text-xs font-semibold text-(--foreground)">
                            {{ message.attendance_label }}
                        </span>
                    </div>

                    <dl class="mt-4 grid gap-3 text-sm sm:grid-cols-2">
                        <div>
                            <dt class="font-semibold text-(--foreground)">{{ t('evaluations.preview.recipients') }}</dt>
                            <dd class="mt-1 break-words text-(--muted-foreground)" dir="ltr">
                                {{ recipientText(message) }}
                            </dd>
                        </div>
                        <div>
                            <dt class="font-semibold text-(--foreground)">{{ t('evaluations.preview.occurrence') }}</dt>
                            <dd class="mt-1 text-(--muted-foreground)">
                                {{ message.occurrence_number ?? t('common.na') }}
                            </dd>
                        </div>
                    </dl>

                    <pre class="mt-4 overflow-x-auto whitespace-pre-wrap break-words rounded-md border border-(--border) bg-(--background) p-3 text-sm leading-7 text-(--foreground)">{{ message.message_content }}</pre>
                </article>
            </div>
        </div>

        <template #footer>
            <Button :label="t('common.close')" severity="secondary" text @click="visible = false" />
        </template>
    </Dialog>
</template>
