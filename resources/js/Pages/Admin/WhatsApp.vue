<script setup>
import axios from 'axios';
import { Head } from '@inertiajs/vue3';
import Button from 'primevue/button';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { adminNavItems } from '../../admin/navItems';
import AdminLayout from '../../components/admin/AdminLayout.vue';
import AdminBreadcrumbs from '../../components/admin/AdminBreadcrumbs.vue';
import { useAppToast } from '../../composables/useAppToast';

const { t } = useI18n();
const appToast = useAppToast();
const props = defineProps({
    device: {
        type: Object,
        default: () => null,
    },
    qr: {
        type: String,
        default: null,
    },
    apiConfigured: {
        type: Boolean,
        default: false,
    },
});

const deviceId = computed(() => props.device?.id ?? null);
const deviceName = computed(() => props.device?.name ?? t('whatsapp.deviceFallback'));
const qrImage = ref(props.qr ?? null);
const phone = ref('');
const message = ref('');
const sending = ref(false);
const deleting = ref(false);
const refreshing = ref(false);
let replayIntervalId = null;

const hasQr = computed(() => Boolean(qrImage.value));
const canSend = computed(() => (
    props.apiConfigured
    && !sending.value
    && phone.value.trim() !== ''
    && message.value.trim() !== ''
));

const stopReplayPolling = () => {
    if (replayIntervalId) {
        window.clearInterval(replayIntervalId);
        replayIntervalId = null;
    }
};

const replayScan = async (silent = false) => {
    if (!deviceId.value || !props.apiConfigured) {
        return;
    }

    refreshing.value = true;

    try {
        const { data } = await axios.get(`/admin/whatsapp/${deviceId.value}/replay-scan`);
        const hadQr = qrImage.value !== null;
        qrImage.value = data?.qr ?? null;

        if (hadQr && data?.connected) {
            appToast.success(t('whatsapp.connected'));
        }
    } catch (error) {
        if (!silent) {
            appToast.fromAxiosError(error, {
                summary: t('notifications.requestFailedTitle'),
                fallback: t('whatsapp.refreshFailed'),
            });
        }
    } finally {
        refreshing.value = false;
    }
};

const startReplayPolling = () => {
    if (typeof window === 'undefined' || replayIntervalId || !hasQr.value || !deviceId.value) {
        return;
    }

    replayIntervalId = window.setInterval(() => {
        replayScan(true);
    }, 30000);
};

const sendMessage = async () => {
    if (!deviceId.value || !canSend.value) {
        return;
    }

    sending.value = true;

    try {
        const { data } = await axios.post(`/admin/whatsapp/${deviceId.value}/send`, {
            phone: phone.value.trim(),
            message: message.value.trim(),
        });

        phone.value = '';
        message.value = '';
        appToast.success(data?.message ?? t('whatsapp.sendSuccess'));
    } catch (error) {
        appToast.fromAxiosError(error, {
            summary: t('notifications.requestFailedTitle'),
            fallback: t('whatsapp.sendFailed'),
        });
    } finally {
        sending.value = false;
    }
};

const deleteDevice = async () => {
    if (!deviceId.value || deleting.value) {
        return;
    }

    if (!window.confirm(t('whatsapp.deleteConfirm', { name: deviceName.value }))) {
        return;
    }

    deleting.value = true;

    try {
        const { data } = await axios.delete(`/admin/whatsapp/${deviceId.value}`);
        appToast.success(data?.message ?? t('whatsapp.deviceDeleted'));
        window.location.reload();
    } catch (error) {
        appToast.fromAxiosError(error, {
            summary: t('notifications.deleteFailedTitle'),
            fallback: t('whatsapp.deleteFailed'),
        });
    } finally {
        deleting.value = false;
    }
};

watch(
    () => props.qr,
    (next) => {
        qrImage.value = next ?? null;
    },
);

watch(
    hasQr,
    (next) => {
        if (next) {
            startReplayPolling();
            return;
        }

        stopReplayPolling();
    },
    { immediate: true },
);

onMounted(() => {
    if (hasQr.value) {
        startReplayPolling();
    }
});

onBeforeUnmount(() => {
    stopReplayPolling();
});
</script>

<template>
    <Head :title="t('whatsapp.title')" />

    <AdminLayout :nav-items="adminNavItems" :page-title="t('whatsapp.title')">
        <section class="space-y-6">
            <AdminBreadcrumbs />

            <article class="rounded-(--radius-base) border border-(--border) bg-(--card) p-6 text-(--card-foreground) shadow-(--shadow-sm)">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 class="text-3xl font-semibold tracking-tight">{{ t('whatsapp.title') }}</h2>
                        <p class="mt-2 text-(--muted-foreground)">{{ t('whatsapp.description') }}</p>
                    </div>

                    <Button
                        type="button"
                        icon="pi pi-trash"
                        severity="danger"
                        outlined
                        :label="t('whatsapp.deleteDevice')"
                        :loading="deleting"
                        @click="deleteDevice"
                    />
                </div>

                <div v-if="!apiConfigured" class="mt-6 rounded-md border border-red-300/60 bg-red-50 px-4 py-3 text-red-800">
                    <p class="font-semibold">{{ t('whatsapp.apiMissing') }}</p>
                    <p class="mt-1 text-sm">{{ t('whatsapp.apiMissingHelp') }}</p>
                </div>

                <div v-else-if="hasQr" class="mt-6 grid gap-6 lg:grid-cols-2">
                    <div>
                        <h3 class="text-2xl font-semibold">{{ t('whatsapp.instructionsTitle') }}</h3>
                        <ol class="mt-4 list-decimal space-y-2 ps-5 text-(--muted-foreground)">
                            <li>{{ t('whatsapp.step1') }}</li>
                            <li>{{ t('whatsapp.step2') }}</li>
                            <li>{{ t('whatsapp.step3') }}</li>
                            <li class="font-semibold text-(--foreground)">{{ t('whatsapp.step4') }}</li>
                        </ol>
                    </div>

                    <div class="rounded-md border border-(--border) bg-(--background) p-5">
                        <div class="flex items-center justify-between gap-3">
                            <p class="text-sm text-(--muted-foreground)">{{ t('whatsapp.scanHint') }}</p>
                            <Button
                                type="button"
                                size="small"
                                outlined
                                icon="pi pi-refresh"
                                :label="t('whatsapp.refreshNow')"
                                :loading="refreshing"
                                @click="replayScan(false)"
                            />
                        </div>

                        <div class="mt-4 flex justify-center">
                            <img :src="qrImage" alt="QR code" class="h-64 w-64 rounded-md border border-(--border) object-contain" />
                        </div>
                    </div>
                </div>

                <div v-else class="mt-6 space-y-4">
                    <div class="rounded-md border border-emerald-300/60 bg-emerald-50 px-4 py-3 text-emerald-800">
                        {{ t('whatsapp.connected') }}
                    </div>

                    <div class="grid gap-4 md:grid-cols-2">
                        <div>
                            <label for="wa-phone" class="mb-2 block text-sm font-medium">{{ t('whatsapp.phone') }}</label>
                            <input
                                id="wa-phone"
                                v-model="phone"
                                type="tel"
                                class="w-full rounded-md border border-(--border) bg-(--background) px-3 py-2"
                                :placeholder="t('whatsapp.phonePlaceholder')"
                            />
                        </div>
                        <div>
                            <label for="wa-message" class="mb-2 block text-sm font-medium">{{ t('whatsapp.message') }}</label>
                            <input
                                id="wa-message"
                                v-model="message"
                                type="text"
                                class="w-full rounded-md border border-(--border) bg-(--background) px-3 py-2"
                                :placeholder="t('whatsapp.messagePlaceholder')"
                            />
                        </div>
                    </div>

                    <div class="flex justify-end">
                        <Button
                            type="button"
                            icon="pi pi-send"
                            :label="t('whatsapp.send')"
                            :disabled="!canSend"
                            :loading="sending"
                            @click="sendMessage"
                        />
                    </div>
                </div>
            </article>
        </section>
    </AdminLayout>
</template>
