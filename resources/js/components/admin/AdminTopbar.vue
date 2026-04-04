<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { router, useForm, usePage } from '@inertiajs/vue3';
import Menu from 'primevue/menu';
import { useI18n } from 'vue-i18n';
import { useSystemSettings } from '../../composables/useSystemSettings';
import { useThemeMode } from '../../composables/useThemeMode';

const props = defineProps({
    collapsed: {
        type: Boolean,
        default: false,
    },
    title: {
        type: String,
        default: '',
    },
    behavior: {
        type: String,
        default: 'default',
    },
});

const emit = defineEmits(['toggleSidebar', 'toggleMobileSidebar']);

const page = usePage();
const { t } = useI18n();
const userName = computed(() => page.props.auth?.user?.name ?? t('topbar.admin'));
const userEmail = computed(() => page.props.auth?.user?.email ?? '');
const userInitials = computed(() => {
    const parts = userName.value.trim().split(/\s+/).filter(Boolean);
    return parts.slice(0, 2).map((part) => part[0]).join('').toUpperCase() || 'AD';
});
const logoutForm = useForm({});
const profileMenu = ref();
const { mode, toggleMode } = useThemeMode();
const { settings, setLanguage, setDirection } = useSystemSettings();
const themeIcon = computed(() => (mode.value === 'dark' ? 'pi pi-sun' : 'pi pi-moon'));
const themeLabel = computed(() => (mode.value === 'dark' ? t('topbar.lightMode') : t('topbar.darkMode')));
const isFullscreen = ref(false);
const nextLanguage = computed(() => (settings.value.language === 'ar' ? 'en' : 'ar'));
const languageToggleLabel = computed(() => (nextLanguage.value === 'ar' ? 'AR' : 'EN'));
const languageToggleTitle = computed(() => (
    nextLanguage.value === 'ar' ? t('settings.arabic') : t('settings.english')
));
const sidebarIcon = computed(() => {
    if (props.behavior === 'condensed' && props.collapsed) {
        return 'pi pi-bars';
    }

    const isRtl = settings.value.direction === 'rtl';

    if (props.collapsed) {
        return isRtl ? 'pi pi-angle-double-left' : 'pi pi-angle-double-right';
    }

    return isRtl ? 'pi pi-angle-double-right' : 'pi pi-angle-double-left';
});
const isHiddenSidebarBehavior = computed(() => props.behavior === 'hidden');
const canToggleDesktopSidebar = computed(() => (
    props.behavior === 'condensed'
));

const syncFullscreenState = () => {
    if (typeof document === 'undefined') return;
    isFullscreen.value = Boolean(document.fullscreenElement);
};

const fullscreenIcon = computed(() => (isFullscreen.value ? 'pi pi-window-minimize' : 'pi pi-window-maximize'));
const fullscreenLabel = computed(() => (isFullscreen.value ? t('topbar.exitFullscreen') : t('topbar.enterFullscreen')));

const toggleFullscreen = async () => {
    if (typeof document === 'undefined') return;

    if (!document.fullscreenElement) {
        await document.documentElement.requestFullscreen?.();
    } else {
        await document.exitFullscreen?.();
    }
};

const updateLanguage = (language) => {
    setLanguage(language);
    if (language === 'ar') setDirection('rtl');
    if (language === 'en') setDirection('ltr');
};

const toggleLanguage = () => {
    updateLanguage(nextLanguage.value);
};

const profileMenuItems = computed(() => [
    {
        label: t('profile.updatePassword'),
        icon: 'pi pi-key',
        command: () => router.get('/admin/password'),
    },
    {
        label: t('topbar.logout'),
        icon: 'pi pi-sign-out',
        severity: 'danger',
        command: () => logoutForm.post('/admin/logout'),
    },
]);

const toggleProfileMenu = (event) => {
    profileMenu.value?.toggle(event);
};

onMounted(() => {
    syncFullscreenState();
    document.addEventListener('fullscreenchange', syncFullscreenState);
});

onBeforeUnmount(() => {
    document.removeEventListener('fullscreenchange', syncFullscreenState);
});
</script>

<template>
    <header class="sticky top-0 z-20 border-b border-(--border) bg-(--card)/95 px-4 py-3 text-(--card-foreground) backdrop-blur sm:px-6">
        <div class="flex min-h-(--topbar-height) items-center justify-between gap-3">
            <div class="flex min-w-0 items-center gap-2">
                <button
                    type="button"
                    class="inline-flex h-11 w-11 items-center justify-center rounded-md bg-transparent text-base text-(--foreground) transition hover:bg-[color-mix(in_oklab,var(--accent)_12%,transparent)]"
                    :class="isHiddenSidebarBehavior ? '' : 'lg:hidden'"
                    :aria-label="t('topbar.openSidebar')"
                    :title="t('topbar.openSidebar')"
                    @click="emit('toggleMobileSidebar')"
                >
                    <i class="pi pi-bars"></i>
                </button>

                <button
                    type="button"
                    v-if="canToggleDesktopSidebar"
                    class="hidden h-11 w-11 items-center justify-center rounded-md bg-transparent text-base text-(--foreground) transition hover:bg-[color-mix(in_oklab,var(--accent)_12%,transparent)] lg:inline-flex"
                    :aria-label="props.collapsed ? t('topbar.expandSidebar') : t('topbar.collapseSidebar')"
                    :title="props.collapsed ? t('topbar.expandSidebar') : t('topbar.collapseSidebar')"
                    @click="emit('toggleSidebar')"
                >
                    <i :class="sidebarIcon"></i>
                </button>

                <div class="min-w-0">
                    <h1 class="truncate text-2xl font-semibold">{{ props.title }}</h1>
                </div>
            </div>

            <div class="flex items-center gap-2 sm:gap-3">
                <button
                    type="button"
                    class="inline-flex h-11 items-center gap-2 rounded-md bg-(--background) px-3 text-sm font-semibold text-(--foreground) transition hover:bg-[color-mix(in_oklab,var(--accent)_12%,transparent)]"
                    :aria-label="languageToggleTitle"
                    :title="languageToggleTitle"
                    @click="toggleLanguage"
                >
                    <i class="pi pi-language text-sm text-(--muted-foreground)"></i>
                    <span>{{ languageToggleLabel }}</span>
                </button>
                <button
                    type="button"
                    class="inline-flex h-11 w-11 items-center justify-center rounded-md bg-transparent text-base text-(--foreground) transition hover:bg-[color-mix(in_oklab,var(--accent)_12%,transparent)]"
                    :aria-label="themeLabel"
                    :title="themeLabel"
                    @click="toggleMode"
                >
                    <i :class="themeIcon"></i>
                </button>
                <button
                    type="button"
                    class="hidden h-11 w-11 items-center justify-center rounded-md bg-transparent text-base text-(--foreground) transition hover:bg-[color-mix(in_oklab,var(--accent)_12%,transparent)] lg:inline-flex"
                    :aria-label="fullscreenLabel"
                    :title="fullscreenLabel"
                    @click="toggleFullscreen"
                >
                    <i :class="fullscreenIcon"></i>
                </button>
                <button
                    type="button"
                    class="rounded-full border-0 bg-transparent p-0 hover:bg-transparent"
                    :aria-label="t('topbar.openProfileMenu')"
                    @click="toggleProfileMenu"
                >
                    <div class="flex h-10 w-10 items-center justify-center rounded-full bg-(--accent) text-sm font-semibold text-(--accent-contrast)">
                        {{ userInitials }}
                    </div>
                </button>
                <Menu ref="profileMenu" :model="profileMenuItems" popup>
                    <template #start>
                        <div class="border-b border-(--border) px-4 py-3">
                            <p class="text-sm font-semibold">{{ userName }}</p>
                            <p class="text-xs text-(--muted-foreground)">{{ userEmail }}</p>
                        </div>
                    </template>
                    <template #item="{ item, props: itemProps }">
                        <a
                            v-ripple
                            v-bind="itemProps.action"
                            class="flex items-center gap-2 rounded-sm px-3 py-2 text-base font-medium transition-colors"
                            :class="
                                item.severity === 'danger'
                                    ? 'text-(--foreground) hover:bg-rose-50 hover:text-rose-600 dark:hover:bg-rose-900/30 dark:hover:text-rose-300'
                                    : 'text-(--foreground) hover:bg-[color-mix(in_oklab,var(--accent)_12%,transparent)]'
                            "
                        >
                            <i :class="[item.icon, 'text-base text-current']"></i>
                            <span class="text-current">{{ item.label }}</span>
                        </a>
                    </template>
                </Menu>
            </div>
        </div>
    </header>
</template>
