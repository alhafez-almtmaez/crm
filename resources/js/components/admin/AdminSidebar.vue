<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref, watch, watchEffect } from 'vue';
import { useI18n } from 'vue-i18n';
import { useSystemSettings } from '../../composables/useSystemSettings';
import { useThemeMode } from '../../composables/useThemeMode';

const SIDEBAR_GROUP_STATE_KEY = 'vita_admin_sidebar_groups_v1';
const SIDEBAR_HOVER_PIN_STATE_KEY = 'vita_admin_sidebar_hover_pinned_v1';
const HOVER_BEHAVIORS = new Set(['small_hover', 'small_hover_active']);
const SIDEBAR_BEHAVIOR_CONFIG = {
    default: {
        desktopMode: 'persistent',
        expandedWidthClass: 'lg:w-(--sidebar-width)',
        collapsedWidthClass: 'lg:w-(--sidebar-collapsed-width)',
        autoHover: false,
        hoverRequiresActive: false,
    },
    condensed: {
        desktopMode: 'persistent',
        expandedWidthClass: 'lg:w-[15rem]',
        collapsedWidthClass: 'lg:w-[4.5rem]',
        autoHover: false,
        hoverRequiresActive: false,
    },
    hidden: {
        desktopMode: 'drawer',
        expandedWidthClass: 'lg:w-(--sidebar-width)',
        collapsedWidthClass: 'lg:w-(--sidebar-collapsed-width)',
        autoHover: false,
        hoverRequiresActive: false,
    },
    small_hover: {
        desktopMode: 'persistent',
        expandedWidthClass: 'lg:w-(--sidebar-width)',
        collapsedWidthClass: 'lg:w-(--sidebar-collapsed-width)',
        autoHover: true,
        hoverRequiresActive: false,
    },
    small_hover_active: {
        desktopMode: 'persistent',
        expandedWidthClass: 'lg:w-(--sidebar-width)',
        collapsedWidthClass: 'lg:w-(--sidebar-collapsed-width)',
        autoHover: true,
        hoverRequiresActive: true,
    },
};

const props = defineProps({
    collapsed: {
        type: Boolean,
        default: false,
    },
    items: {
        type: Array,
        default: () => [],
    },
    mobileOpen: {
        type: Boolean,
        default: false,
    },
    behavior: {
        type: String,
        default: 'default',
    },
});

const emit = defineEmits(['closeMobile']);

const page = usePage();
const { t } = useI18n();
const { settings } = useSystemSettings();
const { mode } = useThemeMode();
const currentPath = computed(() => page.url.split('?')[0]);
const appName = computed(() => settings.value.brandName || page.props.app?.name || t('common.app'));
const logoUrl = computed(() => {
    if (mode.value === 'dark') {
        return settings.value.logoDarkUrl ?? settings.value.logoLightUrl ?? settings.value.logoUrl ?? '';
    }

    return settings.value.logoLightUrl ?? settings.value.logoDarkUrl ?? settings.value.logoUrl ?? '';
});
const iconUrl = computed(() => settings.value.iconUrl ?? '');
const hoverToggleIcon = computed(() => {
    const isRtl = settings.value.direction === 'rtl';

    if (isHoverPinnedOpen.value) {
        return isRtl ? 'pi pi-angle-double-left' : 'pi pi-angle-double-right';
    }

    return isRtl ? 'pi pi-angle-double-right' : 'pi pi-angle-double-left';
});
const groupState = ref({});
const isHoverExpanded = ref(false);
const isHoverPinnedOpen = ref(false);
const isDesktop = ref(false);
const syncViewport = () => {
    if (typeof window === 'undefined') return;
    isDesktop.value = window.innerWidth >= 1024;
};
const behaviorConfig = computed(() => SIDEBAR_BEHAVIOR_CONFIG[props.behavior] ?? SIDEBAR_BEHAVIOR_CONFIG.default);
const isAutoHoverBehavior = computed(() => behaviorConfig.value.autoHover);
const isHiddenBehavior = computed(() => behaviorConfig.value.desktopMode === 'drawer');
const activeNavExists = computed(() => groupedItems.value.some((group) => groupHasActive(group)));
const expandedWidthClass = computed(() => behaviorConfig.value.expandedWidthClass);
const collapsedWidthClass = computed(() => behaviorConfig.value.collapsedWidthClass);
const effectiveCollapsed = computed(() => {
    if (!isDesktop.value) {
        return false;
    }

    if (isHiddenBehavior.value) {
        return !props.mobileOpen;
    }

    if (isAutoHoverBehavior.value) {
        if (isHoverPinnedOpen.value) {
            return false;
        }

        if (behaviorConfig.value.hoverRequiresActive && !activeNavExists.value) {
            return true;
        }

        return !isHoverExpanded.value;
    }

    return props.collapsed;
});
const groupedItems = computed(() => {
    const sourceItems = props.items ?? [];

    if (!Array.isArray(sourceItems) || sourceItems.length === 0) {
        return [];
    }

    if (sourceItems.every((entry) => Array.isArray(entry?.items))) {
        return sourceItems.map((entry, index) => ({
            id: entry.id ?? entry.groupId ?? entry.group ?? entry.groupKey ?? `group-${index}`,
            label: entry.labelKey ? t(entry.labelKey) : (entry.label ?? 'Navigation'),
            collapsible: true,
            items: (entry.items ?? []).map((item) => ({
                ...item,
                label: item.labelKey ? t(item.labelKey) : item.label,
            })),
        }));
    }

    const grouped = [];
    const groupIndex = new Map();

    sourceItems.forEach((item) => {
        if (!item.group && !item.groupKey) {
            grouped.push({
                id: `single:${item.href}`,
                label: '',
                collapsible: false,
                items: [{
                    ...item,
                    label: item.labelKey ? t(item.labelKey) : item.label,
                }],
            });
            return;
        }

        const groupId = item.groupId ?? item.group ?? item.groupKey;
        const groupLabel = item.groupKey ? t(item.groupKey) : item.group;

        if (!groupIndex.has(groupId)) {
            groupIndex.set(groupId, grouped.length);
            grouped.push({
                id: groupId,
                label: groupLabel,
                collapsible: true,
                items: [],
            });
        }

        grouped[groupIndex.get(groupId)].items.push({
            ...item,
            label: item.labelKey ? t(item.labelKey) : item.label,
        });
    });

    return grouped;
});

const isActive = (href) => currentPath.value === href || currentPath.value.startsWith(`${href}/`);
const groupHasActive = (group) => group.items.some((item) => isActive(item.href));
const toggleGroup = (groupId) => {
    groupState.value[groupId] = !groupState.value[groupId];
};

watchEffect(() => {
    const next = { ...groupState.value };

    groupedItems.value.forEach((group) => {
        if (!group.collapsible || Object.prototype.hasOwnProperty.call(next, group.id)) {
            return;
        }

        next[group.id] = groupHasActive(group);
    });

    groupState.value = next;
});

const setHoverExpanded = (next) => {
    if (typeof window !== 'undefined' && window.innerWidth >= 1024 && isAutoHoverBehavior.value) {
        isHoverExpanded.value = next;
    }
};

const togglePinnedSidebar = () => {
    if (!isAutoHoverBehavior.value) return;
    isHoverPinnedOpen.value = !isHoverPinnedOpen.value;
    if (isHoverPinnedOpen.value) {
        isHoverExpanded.value = true;
    }
};

onMounted(() => {
    if (typeof window === 'undefined') return;

    syncViewport();
    window.addEventListener('resize', syncViewport);

    const raw = window.localStorage.getItem(SIDEBAR_GROUP_STATE_KEY);
    if (raw) {
        try {
            const parsed = JSON.parse(raw);
            if (parsed && typeof parsed === 'object' && !Array.isArray(parsed)) {
                groupState.value = parsed;
            }
        } catch {
            // Ignore invalid localStorage payloads.
        }
    }

    const pinned = window.localStorage.getItem(SIDEBAR_HOVER_PIN_STATE_KEY);
    isHoverPinnedOpen.value = pinned === 'true';
});

onBeforeUnmount(() => {
    if (typeof window === 'undefined') return;
    window.removeEventListener('resize', syncViewport);
});

watch(
    groupState,
    (next) => {
        if (typeof window === 'undefined') return;
        window.localStorage.setItem(SIDEBAR_GROUP_STATE_KEY, JSON.stringify(next));
    },
    { deep: true },
);

watch(
    () => props.collapsed,
    (next) => {
        if (!next) isHoverExpanded.value = false;
    },
);

watch(
    isHoverPinnedOpen,
    (next) => {
        if (typeof window === 'undefined') return;
        window.localStorage.setItem(SIDEBAR_HOVER_PIN_STATE_KEY, String(next));
    },
);

watch(
    () => props.behavior,
    (behavior) => {
        if (!HOVER_BEHAVIORS.has(behavior)) {
            isHoverPinnedOpen.value = false;
            isHoverExpanded.value = false;
        }
    },
);

const mobileClosedClass = computed(() => (
    settings.value.direction === 'rtl'
        ? 'translate-x-full lg:translate-x-0'
        : '-translate-x-full lg:translate-x-0'
));
const drawerClosedClass = computed(() => (
    settings.value.direction === 'rtl' ? 'translate-x-full' : '-translate-x-full'
));
const asideBaseClass = computed(() => (
    isHiddenBehavior.value
        ? 'fixed inset-y-0 z-40 w-(--sidebar-width) shrink-0 overflow-hidden bg-(--card) [inset-inline-start:0] [border-inline-end:1px_solid_var(--border)] h-screen transition-[transform,width] duration-300 ease-[cubic-bezier(0.22,1,0.36,1)]'
        : 'fixed inset-y-0 z-40 w-(--sidebar-width) shrink-0 overflow-hidden bg-(--card) [inset-inline-start:0] [border-inline-end:1px_solid_var(--border)] lg:sticky lg:top-0 lg:z-30 lg:h-screen transition-[transform,width] duration-300 ease-[cubic-bezier(0.22,1,0.36,1)]'
));
const overlayVisible = computed(() => props.mobileOpen);
const overlayClass = computed(() => (
    isHiddenBehavior.value
        ? 'fixed inset-0 z-30 bg-black/40'
        : 'fixed inset-0 z-30 bg-black/40 lg:hidden'
));
const translateClass = computed(() => {
    if (isHiddenBehavior.value) {
        return props.mobileOpen ? 'translate-x-0' : drawerClosedClass.value;
    }

    return props.mobileOpen ? 'translate-x-0' : mobileClosedClass.value;
});
</script>

<template>
    <div
        v-if="overlayVisible"
        :class="overlayClass"
        @click="emit('closeMobile')"
    ></div>

    <aside
        :class="[
            asideBaseClass,
            translateClass,
            effectiveCollapsed ? collapsedWidthClass : expandedWidthClass,
        ]"
        @mouseenter="setHoverExpanded(true)"
        @mouseleave="setHoverExpanded(false)"
    >
        <div class="flex h-full flex-col">
            <div class="relative shrink-0 p-5">
                <button
                    v-if="isAutoHoverBehavior && !effectiveCollapsed && !mobileOpen"
                    type="button"
                    class="absolute top-3 inline-flex h-9 w-9 items-center justify-center rounded-sm text-(--muted-foreground) transition hover:bg-[color-mix(in_oklab,var(--accent)_12%,transparent)] hover:text-(--foreground) inset-e-3"
                    :title="isHoverPinnedOpen ? t('topbar.collapseSidebar') : t('topbar.expandSidebar')"
                    :aria-label="isHoverPinnedOpen ? t('topbar.collapseSidebar') : t('topbar.expandSidebar')"
                    @click="togglePinnedSidebar"
                >
                    <i :class="hoverToggleIcon"></i>
                </button>
                <button
                    v-if="mobileOpen"
                    type="button"
                    class="absolute top-3 inline-flex h-9 w-9 items-center justify-center rounded-sm text-(--muted-foreground) transition hover:bg-[color-mix(in_oklab,var(--accent)_12%,transparent)] hover:text-(--foreground) inset-e-3"
                    :title="t('common.close')"
                    :aria-label="t('common.close')"
                    @click="emit('closeMobile')"
                >
                    <i class="pi pi-times"></i>
                </button>
                <div class="flex items-center gap-3" :class="effectiveCollapsed ? 'lg:justify-center' : ''">
                    <div v-if="effectiveCollapsed" class="lg:flex lg:items-center lg:justify-center">
                        <img
                            v-if="iconUrl"
                            :src="iconUrl"
                            :alt="t('settings.currentAppIcon')"
                            class="h-10 w-10 object-contain"
                        />
                    </div>
                    <div :class="effectiveCollapsed ? 'lg:hidden' : ''">
                        <img
                            v-if="logoUrl"
                            :src="logoUrl"
                            :alt="`${appName} logo`"
                            class="h-9 w-auto max-w-44 object-contain"
                        />
                        <p v-else class="text-2xl font-bold leading-tight">{{ appName }}</p>
                    </div>
                </div>
            </div>

            <nav class="min-h-0 flex-1 space-y-4 overflow-y-auto px-3 pb-4">
                <section v-for="group in groupedItems" :key="group.id" class="space-y-1">
                    <button
                        v-if="!effectiveCollapsed && group.collapsible"
                        type="button"
                        class="flex w-full items-center justify-between rounded-sm px-2 py-2 text-sm font-semibold tracking-wide text-(--muted-foreground) uppercase transition-colors duration-200 hover:bg-[color-mix(in_oklab,var(--accent)_8%,transparent)]"
                        @click="toggleGroup(group.id)"
                    >
                        <span>{{ group.label }}</span>
                        <i :class="groupState[group.id] ? 'pi pi-angle-down' : 'pi pi-angle-right'"></i>
                    </button>

                    <div
                        v-if="effectiveCollapsed || !group.collapsible || groupState[group.id]"
                        class="relative space-y-1"
                        :class="
                            effectiveCollapsed || !group.collapsible ? '' : 'before:absolute before:inset-y-2 before:inset-s-[0.85rem] before:w-px before:bg-[color-mix(in_oklab,var(--border)_88%,transparent)]'
                        "
                    >
                        <Link
                            v-for="item in group.items"
                            :key="item.href"
                            :href="item.href"
                            class="relative flex items-center gap-3 rounded-[calc(var(--radius-base)-0.25rem)] py-3.5 text-base font-medium transition-all duration-200"
                            :title="item.label"
                            :class="
                                [
                                    effectiveCollapsed ? 'justify-center px-2' : (group.collapsible ? 'ps-7 pe-3' : 'px-3'),
                                    isActive(item.href)
                                        ? 'bg-(--accent) text-(--accent-contrast) shadow-[inset_0_0_0_1px_color-mix(in_oklab,var(--accent)_65%,white)]'
                                        : 'text-(--muted-foreground) hover:bg-[color-mix(in_oklab,var(--accent)_12%,transparent)] hover:text-(--foreground)',
                                ]
                            "
                            @click="emit('closeMobile')"
                        >
                            <i :class="[item.icon ?? 'pi pi-circle', 'text-sm']"></i>
                            <span :class="['transition-opacity duration-200', effectiveCollapsed ? 'hidden opacity-0' : 'opacity-100']">{{ item.label }}</span>
                        </Link>
                    </div>
                </section>
            </nav>
        </div>
    </aside>
</template>
