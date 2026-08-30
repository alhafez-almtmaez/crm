<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import Toast from 'primevue/toast';
import AdminSidebar from './AdminSidebar.vue';
import AdminTopbar from './AdminTopbar.vue';
import { filterNavItemsByAccess } from '../../admin/navItems';
import { useAppToast } from '../../composables/useAppToast';
import { useSystemSettings } from '../../composables/useSystemSettings';

const SIDEBAR_STATE_KEY = 'vita_admin_sidebar_collapsed';

const readSidebarCollapsedState = () => {
    if (typeof window === 'undefined') {
        return false;
    }

    try {
        return window.localStorage.getItem(SIDEBAR_STATE_KEY) === 'true';
    } catch {
        return false;
    }
};

const writeSidebarCollapsedState = (collapsed) => {
    if (typeof window === 'undefined') {
        return;
    }

    try {
        window.localStorage.setItem(SIDEBAR_STATE_KEY, String(collapsed));
    } catch {
        // Keep the sidebar usable when browser storage is unavailable.
    }
};

const props = defineProps({
    pageTitle: {
        type: String,
        default: 'Dashboard',
    },
    navItems: {
        type: Array,
        default: () => [],
    },
});

const isMobileSidebarOpen = ref(false);
const page = usePage();
const appToast = useAppToast();
const { settings } = useSystemSettings();
const showFlash = (flash) => {
    const success = flash?.success ?? '';
    const error = flash?.error ?? '';

    if (success) {
        appToast.success(success);
    }

    if (error) {
        appToast.error(error);
    }
};
const filteredNavItems = computed(() => {
    const user = page.props.auth?.user;

    return filterNavItemsByAccess(props.navItems, {
        roles: user?.roles ?? [],
        permissions: user?.permissions ?? [],
    });
});
const sidebarBehavior = computed(() => settings.value.sidebarBehavior ?? 'default');
const isAutoCollapsedBehavior = computed(() => ['hidden', 'small_hover_active', 'small_hover'].includes(sidebarBehavior.value));
const isSidebarCollapsed = computed(() => {
    if (sidebarBehavior.value === 'default') {
        return false;
    }

    if (sidebarBehavior.value === 'condensed') {
        return isSidebarUserCollapsed.value;
    }

    return true;
});
const isSidebarUserCollapsed = ref(false);

const restoreSidebarState = () => {
    isSidebarUserCollapsed.value = readSidebarCollapsedState();
};

const toggleSidebar = () => {
    if (isAutoCollapsedBehavior.value || sidebarBehavior.value === 'default') {
        return;
    }

    isSidebarUserCollapsed.value = !isSidebarUserCollapsed.value;

    writeSidebarCollapsedState(isSidebarUserCollapsed.value);
};

const toggleMobileSidebar = () => {
    isMobileSidebarOpen.value = !isMobileSidebarOpen.value;
};

const closeMobileSidebar = () => {
    isMobileSidebarOpen.value = false;
};

const handleResize = () => {
    if (typeof window !== 'undefined' && window.innerWidth >= 1024) {
        isMobileSidebarOpen.value = false;
    }
};

onMounted(() => {
    restoreSidebarState();

    window.addEventListener('resize', handleResize);
    showFlash(page.props.flash);
});

watch(
    sidebarBehavior,
    (behavior) => {
        if (behavior === 'condensed') {
            restoreSidebarState();
        }
    },
    { immediate: true },
);

onBeforeUnmount(() => {
    window.removeEventListener('resize', handleResize);
});

watch(
    () => page.props.flash,
    (flash) => {
        showFlash(flash);
    },
    { deep: true },
);
</script>

<template>
    <main class="min-h-screen bg-(--background) text-(--foreground) transition-colors">
        <Toast position="bottom-right" />
        <div class="flex min-h-screen w-full flex-col lg:flex-row">
            <AdminSidebar
                :items="filteredNavItems"
                :collapsed="isSidebarCollapsed"
                :behavior="sidebarBehavior"
                :mobile-open="isMobileSidebarOpen"
                @close-mobile="closeMobileSidebar"
            />

            <section class="min-w-0 flex-1">
            <AdminTopbar
                    :title="pageTitle"
                    :collapsed="isSidebarCollapsed"
                    :behavior="sidebarBehavior"
                    @toggle-sidebar="toggleSidebar"
                    @toggle-mobile-sidebar="toggleMobileSidebar"
                />
                <div class="p-4 sm:p-6">
                    <slot />
                </div>
            </section>
        </div>
    </main>
</template>
