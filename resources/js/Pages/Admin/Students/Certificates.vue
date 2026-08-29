<script setup>
import axios from 'axios';
import { Head, router } from '@inertiajs/vue3';
import Button from 'primevue/button';
import Dialog from 'primevue/dialog';
import Textarea from 'primevue/textarea';
import { computed, ref } from 'vue';
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
const validAvailableCount = computed(() => props.availableCertificates.filter((item) => item.can_issue).length);
const breadcrumbItems = computed(() => [
    { labelKey: 'breadcrumbs.dashboard', href: '/admin/dashboard' },
    { labelKey: 'breadcrumbs.students', href: '/admin/students' },
    { label: t('certificates.title'), href: `/admin/students/${props.student.id}/certificates` },
]);

const goBack = () => {
    router.get('/admin/students');
};

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

const openUrl = (url) => {
    if (url) {
        window.open(url, '_blank', 'noopener,noreferrer');
    }
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

            <article class="rounded-(--radius-base) border border-(--border) bg-(--card) p-5 text-(--card-foreground) shadow-(--shadow-sm)">
                <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <h3 class="text-xl font-semibold">{{ t('certificates.availableTitle') }}</h3>
                        <p class="mt-1 text-sm text-(--muted-foreground)">{{ t('certificates.availableHint') }}</p>
                    </div>
                    <span class="rounded-full bg-cyan-100 px-3 py-1 text-sm font-bold text-cyan-900">
                        {{ validAvailableCount }}
                    </span>
                </div>

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

            <article class="rounded-(--radius-base) border border-(--border) bg-(--card) p-5 text-(--card-foreground) shadow-(--shadow-sm)">
                <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <h3 class="text-xl font-semibold">{{ t('certificates.issuedTitle') }}</h3>
                        <p class="mt-1 text-sm text-(--muted-foreground)">{{ t('certificates.issuedHint') }}</p>
                    </div>
                    <span class="rounded-full bg-emerald-100 px-3 py-1 text-sm font-bold text-emerald-900">
                        {{ certificates.length }}
                    </span>
                </div>

                <div v-if="certificates.length" class="overflow-x-auto rounded-md border border-(--border)">
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
                            <tr v-for="item in certificates" :key="item.id">
                                <td dir="ltr" class="border-b border-(--border) px-4 py-3 text-end font-mono font-semibold">{{ item.certificate_number }}</td>
                                <td class="border-b border-(--border) px-4 py-3">
                                    <span class="font-semibold">{{ item.achievement_type_label }}:</span>
                                    {{ item.achievement_name }}
                                </td>
                                <td class="border-b border-(--border) px-4 py-3">{{ item.gregorian_date }}</td>
                                <td class="border-b border-(--border) px-4 py-3">{{ item.issued_at }}</td>
                                <td class="border-b border-(--border) px-4 py-3">{{ item.issued_by_name || t('common.na') }}</td>
                                <td class="border-b border-(--border) px-4 py-3">
                                    <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-bold" :class="statusBadgeClass(item.status)">
                                        {{ item.status_label }}
                                    </span>
                                </td>
                                <td class="border-b border-(--border) px-4 py-3">
                                    <div class="flex gap-2">
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
