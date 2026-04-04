import axios from 'axios';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useAppToast } from './useAppToast';

export const useServerTable = ({
    endpoint,
    defaultSortBy = 'id',
    defaultSortDir = 'desc',
    initialPerPage = 10,
    debounceMs = 300,
    extraParams = () => ({}),
} = {}) => {
    const { t } = useI18n();
    const appToast = useAppToast();
    const loading = ref(false);
    const rows = ref([]);
    const totalRecords = ref(0);
    const currentPage = ref(1);
    const rowsPerPage = ref(initialPerPage);
    const search = ref('');
    const sortBy = ref(defaultSortBy);
    const sortDir = ref(defaultSortDir);
    const tableSortOrder = computed(() => (sortDir.value === 'asc' ? 1 : -1));

    let searchTimeoutId = null;

    const fetchRows = async () => {
        loading.value = true;

        try {
            const { data } = await axios.get(endpoint, {
                params: {
                    page: currentPage.value,
                    per_page: rowsPerPage.value,
                    search: search.value,
                    sort_by: sortBy.value,
                    sort_dir: sortDir.value,
                    ...extraParams(),
                },
            });

            rows.value = data.data;
            totalRecords.value = data.meta.total;
            currentPage.value = data.meta.current_page;
            rowsPerPage.value = data.meta.per_page;
        } catch (error) {
            appToast.fromAxiosError(error, {
                summary: t('notifications.loadFailedTitle'),
                fallback: t('notifications.loadFailedDetail'),
            });
        } finally {
            loading.value = false;
        }
    };

    const onPageChange = async ({ page, rows: nextRows }) => {
        currentPage.value = page;
        rowsPerPage.value = nextRows;
        await fetchRows();
    };

    const onSortChange = async ({ sortField, sortOrder }) => {
        if (!sortField || !sortOrder) {
            sortBy.value = defaultSortBy;
            sortDir.value = defaultSortDir;
        } else {
            sortBy.value = sortField;
            sortDir.value = sortOrder === 1 ? 'asc' : 'desc';
        }

        currentPage.value = 1;
        await fetchRows();
    };

    watch(search, () => {
        currentPage.value = 1;

        if (searchTimeoutId) {
            window.clearTimeout(searchTimeoutId);
        }

        searchTimeoutId = window.setTimeout(() => {
            fetchRows();
        }, debounceMs);
    });

    onBeforeUnmount(() => {
        if (searchTimeoutId) {
            window.clearTimeout(searchTimeoutId);
        }
    });

    return {
        loading,
        rows,
        totalRecords,
        currentPage,
        rowsPerPage,
        search,
        sortBy,
        sortDir,
        tableSortOrder,
        fetchRows,
        onPageChange,
        onSortChange,
    };
};
