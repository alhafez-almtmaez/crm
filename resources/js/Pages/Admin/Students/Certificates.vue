<script setup>
import axios from 'axios';
import { Head, router } from '@inertiajs/vue3';
import Button from 'primevue/button';
import Dialog from 'primevue/dialog';
import Textarea from 'primevue/textarea';
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { adminNavItems } from '../../../admin/navItems';
import AdminBreadcrumbs from '../../../components/admin/AdminBreadcrumbs.vue';
import AdminLayout from '../../../components/admin/AdminLayout.vue';
import { useAppToast } from '../../../composables/useAppToast';

const { t } = useI18n();
const appToast = useAppToast();
const props = defineProps({
    student: {
        type: Object,
        required: true,
    },
    availableCertificates: {
        type: Array,
        default: () => [],
    },
    certificates: {
        type: Array,
        default: () => [],
    },
    canIssue: {
        type: Boolean,
        default: false,
    },
    canRedesign: {
        type: Boolean,
        default: false,
    },
    canRevoke: {
        type: Boolean,
        default: false,
    },
    canSendWhatsApp: {
        type: Boolean,
        default: false,
    },
});

const issueDialogVisible = ref(false);
const selectedCheckpoint = ref(null);
const issuing = ref(false);
const redesignDialogVisible = ref(false);
const selectedCertificate = ref(null);
const redesigning = ref(false);
const revokeDialogVisible = ref(false);
const selectedRevokeCertificate = ref(null);
const revokeReason = ref('');
const revoking = ref(false);
const issuedCertificates = ref([...props.certificates]);
const sendingCertificateIds = ref(new Set());
const portalLinkCopied = ref(false);
const activeCertificateTab = ref('available');
const validAvailableCount = computed(() => props.availableCertificates.filter((item) => item.can_issue).length);
const certificateTabOptions = computed(() => [
    {
        value: 'available',
        label: t('certificates.availableTitle'),
        icon: 'pi-id-card',
        count: validAvailableCount.value,
        badgeClass: 'bg-cyan-100 text-cyan-900 dark:bg-cyan-900/40 dark:text-cyan-200',
    },
    {
        value: 'issued',
        label: t('certificates.issuedTitle'),
        icon: 'pi-file-check',
        count: issuedCertificates.value.length,
        badgeClass: 'bg-emerald-100 text-emerald-900 dark:bg-emerald-900/40 dark:text-emerald-200',
    },
]);
const breadcrumbItems = computed(() => [
    { labelKey: 'breadcrumbs.dashboard', href: '/admin/dashboard' },
    { labelKey: 'breadcrumbs.students', href: '/admin/students' },
    { label: t('certificates.title'), href: `/admin/students/${props.student.id}/certificates` },
]);

const goBack = () => {
    router.get('/admin/students');
};

const focusCertificateTab = (index) => {
    const normalizedIndex = (index + certificateTabOptions.value.length) % certificateTabOptions.value.length;
    const tabValue = certificateTabOptions.value[normalizedIndex].value;

    activeCertificateTab.value = tabValue;
    requestAnimationFrame(() => {
        document.getElementById(`student-certificates-tab-${tabValue}`)?.focus();
    });
};

const focusAdjacentCertificateTab = (index, offset) => {
    const directionAwareOffset = document.documentElement.dir === 'rtl' ? -offset : offset;

    focusCertificateTab(index + directionAwareOffset);
};

watch(
    () => props.certificates,
    (certificates) => {
        issuedCertificates.value = [...certificates];
    },
);

const openIssueDialog = (checkpoint) => {
    if (!props.canIssue || !checkpoint?.can_issue) {
        return;
    }

    selectedCheckpoint.value = checkpoint;
    issueDialogVisible.value = true;
};

const issueCertificate = async () => {
    if (!selectedCheckpoint.value || issuing.value) {
        return;
    }

    issuing.value = true;

    try {
        const { data } = await axios.post(`/admin/students/${props.student.id}/certificates`, {
            plan_point_id: selectedCheckpoint.value.id,
        });

        appToast.success(data?.message ?? t('certificates.issueSuccess'));
        issueDialogVisible.value = false;
        selectedCheckpoint.value = null;
        router.reload({
            only: ['availableCertificates', 'certificates'],
            preserveScroll: true,
            onFinish: () => {
                issuing.value = false;
            },
        });
    } catch (error) {
        appToast.fromAxiosError(error, {
            summary: t('notifications.requestFailedTitle'),
            fallback: t('certificates.issueFailed'),
        });
        issuing.value = false;
    }
};

const openRedesignDialog = (certificate) => {
    if (!props.canRedesign || !certificate?.redesign_url || redesigning.value) {
        return;
    }

    selectedCertificate.value = certificate;
    redesignDialogVisible.value = true;
};

const redesignCertificate = async () => {
    if (!selectedCertificate.value?.redesign_url || redesigning.value) {
        return;
    }

    redesigning.value = true;

    try {
        const { data } = await axios.put(selectedCertificate.value.redesign_url);

        appToast.success(data?.message ?? t('certificates.redesignSuccess'));
        redesignDialogVisible.value = false;
        selectedCertificate.value = null;
        router.reload({
            only: ['certificates'],
            preserveScroll: true,
            onFinish: () => {
                redesigning.value = false;
            },
        });
    } catch (error) {
        appToast.fromAxiosError(error, {
            summary: t('notifications.requestFailedTitle'),
            fallback: t('certificates.redesignFailed'),
        });
        redesigning.value = false;
    }
};

const openRevokeDialog = (certificate) => {
    if (!props.canRevoke || !certificate?.revoke_url || revoking.value) {
        return;
    }

    selectedRevokeCertificate.value = certificate;
    revokeReason.value = '';
    revokeDialogVisible.value = true;
};

const revokeCertificate = async () => {
    const reason = revokeReason.value.trim();
    if (!selectedRevokeCertificate.value?.revoke_url || reason.length < 3 || revoking.value) {
        return;
    }

    revoking.value = true;

    try {
        const { data } = await axios.patch(selectedRevokeCertificate.value.revoke_url, {
            revoked_reason: reason,
        });

        appToast.success(data?.message ?? t('certificates.revokeSuccess'));
        revokeDialogVisible.value = false;
        selectedRevokeCertificate.value = null;
        revokeReason.value = '';
        router.reload({
            only: ['certificates'],
            preserveScroll: true,
            onFinish: () => {
                revoking.value = false;
            },
        });
    } catch (error) {
        appToast.fromAxiosError(error, {
            summary: t('notifications.requestFailedTitle'),
            fallback: t('certificates.revokeFailed'),
        });
        revoking.value = false;
    }
};

const statusBadgeClass = (status) => ({
    valid: 'bg-emerald-100 text-emerald-800',
    revoked: 'bg-red-100 text-red-800',
    replaced: 'bg-orange-100 text-orange-800',
}[status] ?? 'bg-slate-100 text-slate-700');

const isSendingCertificate = (certificateId) => sendingCertificateIds.value.has(certificateId);
const certificateWasSent = (certificate) => Boolean(certificate.was_sent_via_whatsapp || certificate.whatsapp_sent_at);
const certificateNeedsDeliveryReview = (certificate) => (
    certificate.whatsapp_delivery_status === 'review_required'
    || certificate.whatsapp_delivery_requires_review
);

const setCertificateSending = (certificateId, sending) => {
    const nextIds = new Set(sendingCertificateIds.value);

    if (sending) {
        nextIds.add(certificateId);
    } else {
        nextIds.delete(certificateId);
    }

    sendingCertificateIds.value = nextIds;
};

const whatsappActionTitle = (certificate) => {
    if (isSendingCertificate(certificate.id) || certificate.whatsapp_delivery_status === 'processing') {
        return t('certificates.whatsappSending');
    }

    if (certificateNeedsDeliveryReview(certificate)) {
        return t('certificates.whatsappNeedsReview');
    }

    if (certificate.whatsapp_delivery_status === 'partial') {
        return t('certificates.whatsappPartiallySent');
    }

    if (certificateWasSent(certificate)) {
        const sentAt = certificate.whatsapp_sent_at_formatted || certificate.whatsapp_sent_at;

        return sentAt
            ? t('certificates.whatsappSentAt', { date: sentAt })
            : t('certificates.whatsappSent');
    }

    if (certificate.status !== 'valid') {
        return t('certificates.whatsappValidOnly');
    }

    if (!props.student.has_whatsapp_recipient) {
        return t('certificates.whatsappNoRecipient');
    }

    if (!certificate.whatsapp_send_url || !certificate.can_send_whatsapp) {
        return t('certificates.whatsappUnavailable');
    }

    return t('certificates.sendViaWhatsApp');
};

const isWhatsAppSendDisabled = (certificate) => (
    isSendingCertificate(certificate.id)
    || certificateWasSent(certificate)
    || Boolean(certificate.whatsapp_delivery_status)
    || certificate.status !== 'valid'
    || !props.student.has_whatsapp_recipient
    || !certificate.whatsapp_send_url
    || !certificate.can_send_whatsapp
);

const whatsappStatusLabel = (certificate) => {
    if (certificate.whatsapp_delivery_status === 'processing') {
        return t('certificates.whatsappSending');
    }

    if (certificateNeedsDeliveryReview(certificate)) {
        return t('certificates.whatsappNeedsReview');
    }

    if (certificate.whatsapp_delivery_status === 'partial') {
        return t('certificates.whatsappPartiallySent');
    }

    return t('certificates.whatsappSent');
};

const whatsappStatusClass = (certificate) => (
    certificateNeedsDeliveryReview(certificate)
    || ['partial', 'processing'].includes(certificate.whatsapp_delivery_status)
        ? 'text-[var(--status-warning)]'
        : 'text-[var(--status-success)]'
);

const whatsappActionIcon = (certificate) => {
    if (certificateNeedsDeliveryReview(certificate)) {
        return 'pi pi-exclamation-triangle';
    }

    return certificateWasSent(certificate) ? 'pi pi-check-circle' : 'pi pi-whatsapp';
};

const sendCertificateViaWhatsApp = async (certificate) => {
    if (
        !props.canSendWhatsApp
        || !certificate
        || isWhatsAppSendDisabled(certificate)
    ) {
        return;
    }

    setCertificateSending(certificate.id, true);

    try {
        const { data } = await axios.post(certificate.whatsapp_send_url);

        if (data?.certificate) {
            issuedCertificates.value = issuedCertificates.value.map((item) => (
                String(item.id) === String(data.certificate.id)
                    ? { ...item, ...data.certificate }
                    : item
            ));
        }

        if (data?.partial || data?.uncertain) {
            appToast.push({
                severity: 'warn',
                summary: t('certificates.whatsappDeliveryWarning'),
                detail: data?.message ?? t('certificates.whatsappNeedsReview'),
                life: 5000,
            });
        } else {
            appToast.success(data?.message ?? t('certificates.whatsappSendSuccess'));
        }
    } catch (error) {
        appToast.fromAxiosError(error, {
            summary: t('notifications.requestFailedTitle'),
            fallback: t('certificates.whatsappSendFailed'),
        });
    } finally {
        setCertificateSending(certificate.id, false);
    }
};

const openUrl = (url) => {
    if (url) {
        window.open(url, '_blank', 'noopener,noreferrer');
    }
};

const copyPortalLink = async () => {
    const url = String(props.student.certificate_portal_url ?? '').trim();
    if (!url) {
        return;
    }

    try {
        await navigator.clipboard.writeText(url);
    } catch {
        const input = document.createElement('input');
        input.value = url;
        input.setAttribute('readonly', '');
        input.style.position = 'fixed';
        input.style.opacity = '0';
        document.body.appendChild(input);
        input.select();
        document.execCommand('copy');
        input.remove();
    }

    portalLinkCopied.value = true;
    window.setTimeout(() => {
        portalLinkCopied.value = false;
    }, 1800);
};
</script>

<template>
    <Head :title="`${t('certificates.title')} - ${student.full_name}`" />

    <AdminLayout :nav-items="adminNavItems" :page-title="t('certificates.title')">
        <section class="space-y-6">
            <AdminBreadcrumbs :items="breadcrumbItems" />

            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-2xl font-bold text-(--foreground)">{{ t('certificates.studentCertificates') }}</h2>
                    <p class="mt-1 text-sm text-(--muted-foreground)">{{ student.full_name }}</p>
                </div>
                <Button
                    type="button"
                    icon="pi pi-arrow-right"
                    severity="secondary"
                    outlined
                    :label="t('certificates.backToStudents')"
                    @click="goBack"
                />
            </div>

            <article class="rounded-(--radius-base) border border-(--border) bg-(--card) p-5 text-(--card-foreground) shadow-(--shadow-sm)">
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <div>
                        <p class="text-xs font-semibold text-(--muted-foreground)">{{ t('students.studentName') }}</p>
                        <p class="mt-1 font-semibold">{{ student.full_name }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold text-(--muted-foreground)">{{ t('groups.center') }}</p>
                        <p class="mt-1 font-semibold">{{ student.center_name || t('common.na') }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold text-(--muted-foreground)">{{ t('students.plan') }}</p>
                        <p class="mt-1 font-semibold">{{ student.plan_name || t('common.na') }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold text-(--muted-foreground)">{{ t('students.currentPlanPoint') }}</p>
                        <p class="mt-1 font-semibold">{{ student.current_plan_point_name || t('certificates.notStarted') }}</p>
                    </div>
                </div>
            </article>

            <article
                v-if="student.certificate_portal_url"
                class="rounded-(--radius-base) border border-[color-mix(in_oklab,var(--accent)_28%,var(--border))] bg-[color-mix(in_oklab,var(--accent)_4%,var(--card))] p-5 text-(--card-foreground) shadow-(--shadow-sm)"
            >
                <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                    <div class="flex min-w-0 items-start gap-3">
                        <span class="grid size-11 shrink-0 place-items-center rounded-xl bg-[color-mix(in_oklab,var(--accent)_12%,transparent)] text-[var(--accent)]">
                            <i class="pi pi-link text-lg" aria-hidden="true"></i>
                        </span>
                        <div class="min-w-0">
                            <h3 class="font-bold text-(--foreground)">{{ t('certificates.portalTitle', { name: student.full_name }) }}</h3>
                            <p class="mt-1 text-sm leading-6 text-(--muted-foreground)">{{ t('certificates.portalHint') }}</p>
                        </div>
                    </div>

                    <div class="flex shrink-0 flex-wrap gap-2">
                        <Button
                            type="button"
                            severity="secondary"
                            outlined
                            :icon="portalLinkCopied ? 'pi pi-check' : 'pi pi-copy'"
                            :label="portalLinkCopied ? t('certificates.portalLinkCopied') : t('certificates.copyPortalLink')"
                            @click="copyPortalLink"
                        />
                        <Button
                            type="button"
                            icon="pi pi-external-link"
                            :label="t('certificates.openPortal')"
                            @click="openUrl(student.certificate_portal_url)"
                        />
                    </div>
                </div>

                <div
                    dir="ltr"
                    class="mt-4 overflow-x-auto rounded-lg border border-(--border) bg-(--background) px-3 py-2.5 text-start font-mono text-xs text-(--muted-foreground) sm:text-sm"
                >
                    {{ student.certificate_portal_url }}
                </div>
                <span class="sr-only" aria-live="polite">{{ portalLinkCopied ? t('certificates.portalLinkCopied') : '' }}</span>
            </article>

            <nav
                class="rounded-(--radius-base) border border-(--border) bg-(--card) p-1.5 shadow-(--shadow-sm)"
                role="tablist"
                :aria-label="t('certificates.studentCertificates')"
            >
                <div class="grid grid-cols-2 gap-1">
                    <button
                        v-for="(tab, index) in certificateTabOptions"
                        :id="`student-certificates-tab-${tab.value}`"
                        :key="tab.value"
                        type="button"
                        role="tab"
                        class="flex min-h-14 min-w-0 flex-wrap items-center justify-center gap-2 rounded-lg px-3 py-2.5 text-sm font-semibold transition sm:text-base"
                        :class="activeCertificateTab === tab.value
                            ? 'bg-[color-mix(in_oklab,var(--accent)_12%,transparent)] text-[var(--accent)] ring-1 ring-inset ring-[color-mix(in_oklab,var(--accent)_24%,transparent)]'
                            : 'text-(--muted-foreground) hover:bg-[color-mix(in_oklab,var(--accent)_6%,transparent)] hover:text-(--foreground)'"
                        :aria-selected="activeCertificateTab === tab.value"
                        :aria-controls="`student-certificates-panel-${tab.value}`"
                        :tabindex="activeCertificateTab === tab.value ? 0 : -1"
                        @click="activeCertificateTab = tab.value"
                        @keydown.left.prevent="focusAdjacentCertificateTab(index, -1)"
                        @keydown.right.prevent="focusAdjacentCertificateTab(index, 1)"
                        @keydown.home.prevent="focusCertificateTab(0)"
                        @keydown.end.prevent="focusCertificateTab(certificateTabOptions.length - 1)"
                    >
                        <i class="pi" :class="tab.icon" aria-hidden="true"></i>
                        <span class="leading-5">{{ tab.label }}</span>
                        <span class="rounded-full px-2.5 py-0.5 text-xs font-bold" :class="tab.badgeClass">
                            {{ tab.count }}
                        </span>
                    </button>
                </div>
            </nav>

            <article
                v-show="activeCertificateTab === 'available'"
                id="student-certificates-panel-available"
                class="rounded-(--radius-base) border border-(--border) bg-(--card) p-5 text-(--card-foreground) shadow-(--shadow-sm)"
                role="tabpanel"
                aria-labelledby="student-certificates-tab-available"
                tabindex="0"
            >
                <p class="mb-4 text-sm text-(--muted-foreground)">{{ t('certificates.availableHint') }}</p>

                <div v-if="availableCertificates.length" class="overflow-x-auto rounded-md border border-(--border)">
                    <table class="min-w-full border-separate border-spacing-0 text-sm">
                        <thead class="bg-(--background)">
                            <tr>
                                <th class="border-b border-(--border) px-4 py-3 text-start font-semibold">{{ t('certificates.planCheckpoint') }}</th>
                                <th class="border-b border-(--border) px-4 py-3 text-start font-semibold">{{ t('certificates.achievementType') }}</th>
                                <th class="border-b border-(--border) px-4 py-3 text-start font-semibold">{{ t('certificates.achievementName') }}</th>
                                <th class="border-b border-(--border) px-4 py-3 text-start font-semibold">{{ t('common.actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="checkpoint in availableCertificates" :key="checkpoint.id">
                                <td class="border-b border-(--border) px-4 py-3 font-medium">{{ checkpoint.plan_point_name }}</td>
                                <td class="border-b border-(--border) px-4 py-3">{{ checkpoint.achievement_type_label || t('common.na') }}</td>
                                <td class="border-b border-(--border) px-4 py-3">
                                    <span v-if="checkpoint.can_issue">{{ checkpoint.achievement_name }}</span>
                                    <span v-else class="text-red-700">{{ checkpoint.issue_problem }}</span>
                                </td>
                                <td class="border-b border-(--border) px-4 py-3">
                                    <Button
                                        type="button"
                                        size="small"
                                        icon="pi pi-id-card"
                                        :label="t('certificates.issue')"
                                        :disabled="!canIssue || !checkpoint.can_issue"
                                        @click="openIssueDialog(checkpoint)"
                                    />
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-else class="rounded-md border border-dashed border-(--border) px-4 py-8 text-center text-sm text-(--muted-foreground)">
                    {{ student.current_plan_point_name ? t('certificates.noAvailable') : t('certificates.noProgress') }}
                </div>

                <p v-if="!canIssue" class="mt-3 text-sm text-amber-700">{{ t('certificates.viewOnlyNotice') }}</p>
            </article>

            <article
                v-show="activeCertificateTab === 'issued'"
                id="student-certificates-panel-issued"
                class="rounded-(--radius-base) border border-(--border) bg-(--card) p-5 text-(--card-foreground) shadow-(--shadow-sm)"
                role="tabpanel"
                aria-labelledby="student-certificates-tab-issued"
                tabindex="0"
            >
                <p class="mb-4 text-sm text-(--muted-foreground)">{{ t('certificates.issuedHint') }}</p>

                <div v-if="issuedCertificates.length" class="overflow-x-auto rounded-md border border-(--border)">
                    <table class="min-w-full border-separate border-spacing-0 text-sm">
                        <thead class="bg-(--background)">
                            <tr>
                                <th class="border-b border-(--border) px-4 py-3 text-start font-semibold">{{ t('certificates.number') }}</th>
                                <th class="border-b border-(--border) px-4 py-3 text-start font-semibold">{{ t('certificates.achievement') }}</th>
                                <th class="border-b border-(--border) px-4 py-3 text-start font-semibold">{{ t('certificates.achievementDate') }}</th>
                                <th class="border-b border-(--border) px-4 py-3 text-start font-semibold">{{ t('certificates.issuedAt') }}</th>
                                <th class="border-b border-(--border) px-4 py-3 text-start font-semibold">{{ t('certificates.issuedBy') }}</th>
                                <th class="border-b border-(--border) px-4 py-3 text-start font-semibold">{{ t('certificates.status') }}</th>
                                <th class="border-b border-(--border) px-4 py-3 text-start font-semibold">{{ t('common.actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="item in issuedCertificates" :key="item.id">
                                <td dir="ltr" class="border-b border-(--border) px-4 py-3 text-end font-mono font-semibold">{{ item.certificate_number }}</td>
                                <td class="border-b border-(--border) px-4 py-3">
                                    <span class="font-semibold">{{ item.achievement_type_label }}:</span>
                                    {{ item.achievement_name }}
                                </td>
                                <td class="border-b border-(--border) px-4 py-3">{{ item.gregorian_date }}</td>
                                <td class="border-b border-(--border) px-4 py-3">{{ item.issued_at }}</td>
                                <td class="border-b border-(--border) px-4 py-3">{{ item.issued_by_name || t('common.na') }}</td>
                                <td class="border-b border-(--border) px-4 py-3">
                                    <div class="flex flex-col items-start gap-1.5">
                                        <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-bold" :class="statusBadgeClass(item.status)">
                                            {{ item.status_label }}
                                        </span>
                                        <span
                                            v-if="certificateWasSent(item) || Boolean(item.whatsapp_delivery_status)"
                                            class="inline-flex items-center gap-1 text-xs font-semibold"
                                            :class="whatsappStatusClass(item)"
                                            :title="whatsappActionTitle(item)"
                                        >
                                            <i class="pi pi-whatsapp" aria-hidden="true"></i>
                                            {{ whatsappStatusLabel(item) }}
                                        </span>
                                    </div>
                                </td>
                                <td class="border-b border-(--border) px-4 py-3">
                                    <div class="flex flex-wrap gap-2">
                                        <span
                                            v-if="canSendWhatsApp"
                                            :title="whatsappActionTitle(item)"
                                            class="inline-flex"
                                        >
                                            <Button
                                                type="button"
                                                size="small"
                                                severity="success"
                                                outlined
                                                :icon="whatsappActionIcon(item)"
                                                :loading="isSendingCertificate(item.id)"
                                                :disabled="isWhatsAppSendDisabled(item)"
                                                :title="whatsappActionTitle(item)"
                                                :aria-label="whatsappActionTitle(item)"
                                                @click="sendCertificateViaWhatsApp(item)"
                                            />
                                        </span>
                                        <Button
                                            v-if="canRedesign"
                                            type="button"
                                            size="small"
                                            severity="info"
                                            outlined
                                            icon="pi pi-sync"
                                            :loading="redesigning && selectedCertificate?.id === item.id"
                                            :disabled="redesigning"
                                            :title="t('certificates.redesign')"
                                            :aria-label="t('certificates.redesign')"
                                            @click="openRedesignDialog(item)"
                                        />
                                        <Button
                                            v-if="canRevoke && item.revoke_url"
                                            type="button"
                                            size="small"
                                            severity="danger"
                                            outlined
                                            icon="pi pi-ban"
                                            :loading="revoking && selectedRevokeCertificate?.id === item.id"
                                            :disabled="revoking"
                                            :title="t('certificates.revoke')"
                                            :aria-label="t('certificates.revoke')"
                                            @click="openRevokeDialog(item)"
                                        />
                                        <Button
                                            type="button"
                                            size="small"
                                            severity="secondary"
                                            outlined
                                            icon="pi pi-eye"
                                            :title="t('certificates.preview')"
                                            :aria-label="t('certificates.preview')"
                                            @click="openUrl(item.preview_url)"
                                        />
                                        <Button
                                            type="button"
                                            size="small"
                                            severity="danger"
                                            outlined
                                            icon="pi pi-file-pdf"
                                            :title="t('certificates.downloadPdf')"
                                            :aria-label="t('certificates.downloadPdf')"
                                            @click="openUrl(item.pdf_url)"
                                        />
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-else class="rounded-md border border-dashed border-(--border) px-4 py-8 text-center text-sm text-(--muted-foreground)">
                    {{ t('certificates.noIssued') }}
                </div>
            </article>

            <Dialog
                v-model:visible="issueDialogVisible"
                modal
                :header="t('certificates.confirmTitle')"
                :style="{ width: 'min(32rem, 96vw)' }"
            >
                <div v-if="selectedCheckpoint" class="space-y-4">
                    <p>{{ t('certificates.confirmMessage', { name: student.full_name }) }}</p>
                    <div class="rounded-md border border-(--border) bg-(--background) p-4">
                        <p class="font-semibold">{{ selectedCheckpoint.achievement_type_label }}: {{ selectedCheckpoint.achievement_name }}</p>
                        <p class="mt-1 text-sm text-(--muted-foreground)">{{ selectedCheckpoint.plan_point_name }}</p>
                    </div>
                    <div class="flex justify-end gap-2">
                        <Button type="button" severity="secondary" text :label="t('common.cancel')" :disabled="issuing" @click="issueDialogVisible = false" />
                        <Button type="button" icon="pi pi-id-card" :label="t('certificates.issueNow')" :loading="issuing" @click="issueCertificate" />
                    </div>
                </div>
            </Dialog>

            <Dialog
                v-model:visible="redesignDialogVisible"
                modal
                :header="t('certificates.redesignConfirmTitle')"
                :style="{ width: 'min(34rem, 96vw)' }"
                :closable="!redesigning"
                :close-on-escape="!redesigning"
            >
                <div v-if="selectedCertificate" class="space-y-4">
                    <p>{{ t('certificates.redesignConfirmMessage') }}</p>
                    <div class="rounded-md border border-(--border) bg-(--background) p-4">
                        <p dir="ltr" class="text-end font-mono font-semibold">{{ selectedCertificate.certificate_number }}</p>
                        <p class="mt-1 text-sm text-(--muted-foreground)">
                            {{ selectedCertificate.achievement_type_label }}: {{ selectedCertificate.achievement_name }}
                        </p>
                    </div>
                    <p class="text-sm text-(--muted-foreground)">{{ t('certificates.redesignPreservesContent') }}</p>
                    <div class="flex justify-end gap-2">
                        <Button
                            type="button"
                            severity="secondary"
                            text
                            :label="t('common.cancel')"
                            :disabled="redesigning"
                            @click="redesignDialogVisible = false"
                        />
                        <Button
                            type="button"
                            severity="info"
                            icon="pi pi-sync"
                            :label="t('certificates.redesignNow')"
                            :loading="redesigning"
                            @click="redesignCertificate"
                        />
                    </div>
                </div>
            </Dialog>

            <Dialog
                v-model:visible="revokeDialogVisible"
                modal
                :header="t('certificates.revokeConfirmTitle')"
                :style="{ width: 'min(34rem, 96vw)' }"
                :closable="!revoking"
                :close-on-escape="!revoking"
            >
                <div v-if="selectedRevokeCertificate" class="space-y-4">
                    <p>{{ t('certificates.revokeConfirmMessage') }}</p>
                    <div class="rounded-md border border-(--border) bg-(--background) p-4">
                        <p dir="ltr" class="text-end font-mono font-semibold">{{ selectedRevokeCertificate.certificate_number }}</p>
                        <p class="mt-1 text-sm text-(--muted-foreground)">
                            {{ selectedRevokeCertificate.achievement_type_label }}: {{ selectedRevokeCertificate.achievement_name }}
                        </p>
                    </div>
                    <div>
                        <label for="certificate-revoked-reason" class="mb-2 block text-sm font-semibold">
                            {{ t('certificates.revokeReason') }}
                        </label>
                        <Textarea
                            id="certificate-revoked-reason"
                            v-model="revokeReason"
                            rows="4"
                            maxlength="1000"
                            class="w-full"
                            :placeholder="t('certificates.revokeReasonPlaceholder')"
                            :disabled="revoking"
                        />
                        <p class="mt-1 text-xs text-(--muted-foreground)">{{ t('certificates.revokeReasonPrivacy') }}</p>
                    </div>
                    <div class="flex justify-end gap-2">
                        <Button
                            type="button"
                            severity="secondary"
                            text
                            :label="t('common.cancel')"
                            :disabled="revoking"
                            @click="revokeDialogVisible = false"
                        />
                        <Button
                            type="button"
                            severity="danger"
                            icon="pi pi-ban"
                            :label="t('certificates.revokeNow')"
                            :loading="revoking"
                            :disabled="revokeReason.trim().length < 3"
                            @click="revokeCertificate"
                        />
                    </div>
                </div>
            </Dialog>
        </section>
    </AdminLayout>
</template>
