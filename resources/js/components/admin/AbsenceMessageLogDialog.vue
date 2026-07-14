<script setup>
import Button from 'primevue/button';
import Dialog from 'primevue/dialog';
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    modelValue: {
        type: Boolean,
        default: false,
    },
    logs: {
        type: Array,
        default: () => [],
    },
    evaluation: {
        type: Object,
        default: null,
    },
    loading: {
        type: Boolean,
        default: false,
    },
    resendingLogId: {
        type: Number,
        default: null,
    },
});

const emit = defineEmits(['update:modelValue', 'resend']);
const { t } = useI18n();
const expandedLogIds = ref([]);

const visible = computed({
    get: () => props.modelValue,
    set: (value) => emit('update:modelValue', value),
});

const hasEvaluationDetails = computed(() => Boolean(props.evaluation));

watch(
    () => [props.logs, props.modelValue],
    () => {
        expandedLogIds.value = [];
    },
);

const recipientText = (log) => {
    const phones = Array.isArray(log.recipient_phones) ? log.recipient_phones : [];
    const recipients = [...phones];

    if (log.sent_to_group) {
        recipients.push(log.group_serialized || t('evaluations.preview.centerGroup'));
    }

    return recipients.length > 0 ? recipients.join(', ') : t('common.na');
};

const statusKey = (log) => {
    if (log.local_preview) {
        return 'localPreview';
    }

    if (log.was_message_sent) {
        return 'sent';
    }

    if (log.error) {
        return 'failed';
    }

    return 'notSent';
};

const statusLabel = (log) => t(`evaluations.messageLog.statuses.${statusKey(log)}`);

const statusClass = (log) => ({
    sent: 'bg-emerald-700 text-white',
    localPreview: 'bg-sky-700 text-white',
    failed: 'bg-red-700 text-white',
    notSent: 'bg-amber-600 text-white',
}[statusKey(log)]);

const canResend = (log) => statusKey(log) === 'failed';

const isExpanded = (log) => expandedLogIds.value.includes(log.id);

const toggleLog = (log) => {
    if (isExpanded(log)) {
        expandedLogIds.value = expandedLogIds.value.filter((id) => id !== log.id);
        return;
    }

    expandedLogIds.value = [...expandedLogIds.value, log.id];
};

const actionLabel = (log) => {
    const actions = {
        send_message: t('evaluations.messageLog.actions.sendMessage'),
        send_message_and_freeze: t('evaluations.messageLog.actions.sendMessageAndFreeze'),
        freeze_student: t('evaluations.messageLog.actions.freezeStudent'),
        dismiss_student: t('evaluations.messageLog.actions.dismissStudent'),
    };

    return actions[log.action] ?? (log.action || t('common.na'));
};
</script>

<template>
    <Dialog
        v-model:visible="visible"
        modal
        dismissable-mask
        :header="t('evaluations.messageLog.title')"
        :style="{ width: 'min(1040px, 96vw)' }"
    >
        <div class="space-y-4">
            <section
                v-if="hasEvaluationDetails"
                class="rounded-md border border-(--border) bg-(--background) p-4 text-sm"
            >
                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <h3 class="text-base font-semibold text-(--foreground)">
                            {{ t('evaluations.messageLog.evaluationDetails') }}
                        </h3>
                        <p class="mt-1 text-(--muted-foreground)">
                            {{ props.evaluation.center_name || t('common.na') }}
                        </p>
                    </div>

                    <span
                        class="inline-flex w-fit items-center rounded-full px-2.5 py-1 text-xs font-semibold"
                        :class="props.evaluation.alert_status_badge_class || 'bg-[color-mix(in_oklab,var(--accent)_14%,transparent)] text-(--foreground)'"
                    >
                        {{ props.evaluation.alert_status_label || t('common.na') }}
                    </span>
                </div>

                <dl class="mt-4 grid gap-3 sm:grid-cols-4">
                    <div>
                        <dt class="font-semibold text-(--foreground)">{{ t('common.id') }}</dt>
                        <dd class="mt-1 text-(--muted-foreground)">{{ props.evaluation.id ?? t('common.na') }}</dd>
                    </div>
                    <div>
                        <dt class="font-semibold text-(--foreground)">{{ t('evaluations.date') }}</dt>
                        <dd class="mt-1 text-(--muted-foreground)">{{ props.evaluation.date_formatted || t('common.na') }}</dd>
                    </div>
                    <div>
                        <dt class="font-semibold text-(--foreground)">{{ t('students.admin') }}</dt>
                        <dd class="mt-1 text-(--muted-foreground)">{{ props.evaluation.admin_name || t('common.na') }}</dd>
                    </div>
                    <div>
                        <dt class="font-semibold text-(--foreground)">{{ t('evaluations.createdAt') }}</dt>
                        <dd class="mt-1 text-(--muted-foreground)">{{ props.evaluation.created_at_formatted || t('common.na') }}</dd>
                    </div>
                </dl>
            </section>

            <div v-if="loading" class="py-12 text-center text-sm text-(--muted-foreground)">
                {{ t('common.loading') }}
            </div>

            <div v-else-if="logs.length === 0" class="py-12 text-center text-sm text-(--muted-foreground)">
                {{ t('evaluations.messageLog.empty') }}
            </div>

            <div v-else class="grid gap-3">
                <article
                    v-for="log in logs"
                    :key="log.id"
                    class="overflow-hidden rounded-md border border-(--border) bg-(--card) text-(--card-foreground)"
                >
                    <button
                        type="button"
                        class="flex w-full flex-col gap-3 p-4 text-start transition-colors hover:bg-[color-mix(in_oklab,var(--accent)_8%,transparent)] sm:flex-row sm:items-start sm:justify-between"
                        :aria-expanded="isExpanded(log)"
                        @click="toggleLog(log)"
                    >
                        <span class="min-w-0">
                            <span class="block text-base font-semibold leading-6">{{ log.student_name }}</span>
                            <span class="mt-1 block text-sm leading-6 text-(--muted-foreground)">
                                {{ log.center_name || t('common.na') }}
                            </span>
                        </span>

                        <span class="flex flex-wrap items-center gap-2">
                            <span class="inline-flex w-fit items-center rounded-full px-2.5 py-1 text-xs font-semibold" :class="statusClass(log)">
                                {{ statusLabel(log) }}
                            </span>
                            <span class="inline-flex w-fit items-center rounded-full bg-[color-mix(in_oklab,var(--accent)_14%,transparent)] px-2.5 py-1 text-xs font-semibold text-(--foreground)">
                                {{ log.attendance_label }}
                            </span>
                            <i :class="isExpanded(log) ? 'pi pi-angle-up' : 'pi pi-angle-down'" class="text-sm text-(--muted-foreground)"></i>
                        </span>
                    </button>

                    <div v-if="isExpanded(log)" class="border-t border-(--border) p-4">
                        <dl class="grid gap-3 text-sm sm:grid-cols-2">
                            <div>
                                <dt class="font-semibold text-(--foreground)">{{ t('evaluations.preview.recipients') }}</dt>
                                <dd class="mt-1 break-words text-(--muted-foreground)" dir="ltr">
                                    {{ recipientText(log) }}
                                </dd>
                            </div>
                            <div>
                                <dt class="font-semibold text-(--foreground)">{{ t('evaluations.messageLog.executionTime') }}</dt>
                                <dd class="mt-1 text-(--muted-foreground)">
                                    {{ log.executed_at_formatted || log.created_at_formatted || t('common.na') }}
                                </dd>
                            </div>
                            <div>
                                <dt class="font-semibold text-(--foreground)">{{ t('evaluations.messageLog.action') }}</dt>
                                <dd class="mt-1 text-(--muted-foreground)">
                                    {{ actionLabel(log) }}
                                </dd>
                            </div>
                            <div>
                                <dt class="font-semibold text-(--foreground)">{{ t('evaluations.preview.occurrence') }}</dt>
                                <dd class="mt-1 text-(--muted-foreground)">
                                    {{ log.occurrence_number ?? t('common.na') }}
                                </dd>
                            </div>
                        </dl>

                        <p v-if="log.error" class="mt-4 rounded-md border border-red-300 bg-red-50 px-3 py-2 text-sm text-red-800">
                            {{ log.error }}
                        </p>

                        <div v-if="canResend(log)" class="mt-4 flex justify-end">
                            <Button
                                type="button"
                                icon="pi pi-send"
                                :label="t('evaluations.messageLog.resend')"
                                severity="danger"
                                size="small"
                                outlined
                                :loading="props.resendingLogId === log.id"
                                :disabled="props.resendingLogId !== null"
                                @click.stop="emit('resend', log)"
                            />
                        </div>

                        <pre class="mt-4 overflow-x-auto whitespace-pre-wrap break-words rounded-md border border-(--border) bg-(--background) p-3 text-sm leading-7 text-(--foreground)">{{ log.message_content || t('evaluations.messageLog.noContent') }}</pre>
                    </div>
                </article>
            </div>
        </div>

        <template #footer>
            <Button :label="t('common.close')" severity="secondary" text @click="visible = false" />
        </template>
    </Dialog>
</template>
