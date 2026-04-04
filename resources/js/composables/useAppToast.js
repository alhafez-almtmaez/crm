import { useToast } from 'primevue/usetoast';
import { useI18n } from 'vue-i18n';

const extractErrorDetail = (error, fallback) => {
    const firstValidationMessage = Object.values(error?.response?.data?.errors ?? {})
        .flat()
        .find((message) => typeof message === 'string' && message.length > 0);

    return (
        error?.response?.data?.message
        ?? firstValidationMessage
        ?? error?.message
        ?? fallback
    );
};

export const useAppToast = () => {
    const { t } = useI18n();
    const toast = useToast();

    const push = ({
        severity = 'info',
        summary = t('common.info'),
        detail = '',
        life = 2400,
    }) => {
        toast.add({ severity, summary, detail, life });
    };

    const success = (detail, summary = t('common.success'), life = 2200) => {
        push({ severity: 'success', summary, detail, life });
    };

    const error = (detail, summary = t('common.error'), life = 2800) => {
        push({ severity: 'error', summary, detail, life });
    };

    const info = (detail, summary = t('common.info'), life = 2200) => {
        push({ severity: 'info', summary, detail, life });
    };

    const fromAxiosError = (err, {
        summary = t('notifications.requestFailedTitle'),
        fallback = t('notifications.requestFailedDetail'),
        life = 2800,
    } = {}) => {
        error(extractErrorDetail(err, fallback), summary, life);
    };

    return {
        push,
        success,
        error,
        info,
        fromAxiosError,
    };
};
