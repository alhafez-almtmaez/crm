import { n as useThemeMode, r as useAppToast, t as useSystemSettings } from "./useSystemSettings-CGKqSGLN.js";
import { computed, createBlock, createVNode, mergeProps, onBeforeUnmount, onMounted, openBlock, ref, resolveDirective, toDisplayString, unref, useSSRContext, watch, watchEffect, withCtx, withDirectives } from "vue";
import { ssrGetDirectiveProps, ssrInterpolate, ssrRenderAttr, ssrRenderAttrs, ssrRenderClass, ssrRenderComponent, ssrRenderList, ssrRenderSlot } from "vue/server-renderer";
import { Link, router, useForm, usePage } from "@inertiajs/vue3";
import { useI18n } from "vue-i18n";
import Toast from "primevue/toast";
import Menu from "primevue/menu";
//#region resources/js/admin/navItems.js
var adminNavItems = [
	{
		labelKey: "nav.dashboard",
		href: "/admin/dashboard",
		icon: "pi pi-th-large",
		permissions: ["view admin dashboard"]
	},
	{
		groupId: "management",
		groupKey: "nav.management",
		labelKey: "nav.users",
		href: "/admin/users",
		icon: "pi pi-users",
		permissions: ["users.view"]
	},
	{
		groupId: "management",
		groupKey: "nav.management",
		labelKey: "nav.roles",
		href: "/admin/roles",
		icon: "pi pi-shield",
		permissions: ["roles.view"]
	},
	{
		groupId: "management",
		groupKey: "nav.management",
		labelKey: "nav.plans",
		href: "/admin/plans",
		icon: "pi pi-bookmark",
		permissions: ["plans.view"]
	},
	{
		groupId: "management",
		groupKey: "nav.management",
		labelKey: "nav.activityLogs",
		href: "/admin/activity-logs",
		icon: "pi pi-history",
		permissions: ["activity_logs.view"]
	},
	{
		labelKey: "nav.whatsapp",
		href: "/admin/whatsapp",
		icon: "pi pi-whatsapp",
		roles: ["admin"]
	},
	{
		labelKey: "nav.settings",
		href: "/admin/settings",
		icon: "pi pi-cog",
		roles: ["admin"]
	}
];
var filterNavItemsByAccess = (items, access = {}) => {
	const userRoles = new Set(access.roles ?? []);
	const userPermissions = new Set(access.permissions ?? []);
	return (items ?? []).filter((item) => {
		const roleAllowed = !item.roles?.length || item.roles.some((role) => userRoles.has(role));
		const permissionAllowed = !item.permissions?.length || item.permissions.some((permission) => userPermissions.has(permission));
		return roleAllowed && permissionAllowed;
	});
};
//#endregion
//#region resources/js/components/admin/AdminSidebar.vue
var SIDEBAR_GROUP_STATE_KEY = "vita_admin_sidebar_groups_v1";
var SIDEBAR_HOVER_PIN_STATE_KEY = "vita_admin_sidebar_hover_pinned_v1";
var _sfc_main$2 = {
	__name: "AdminSidebar",
	__ssrInlineRender: true,
	props: {
		collapsed: {
			type: Boolean,
			default: false
		},
		items: {
			type: Array,
			default: () => []
		},
		mobileOpen: {
			type: Boolean,
			default: false
		},
		behavior: {
			type: String,
			default: "default"
		}
	},
	emits: ["closeMobile"],
	setup(__props, { emit: __emit }) {
		const HOVER_BEHAVIORS = new Set(["small_hover", "small_hover_active"]);
		const SIDEBAR_BEHAVIOR_CONFIG = {
			default: {
				desktopMode: "persistent",
				expandedWidthClass: "lg:w-(--sidebar-width)",
				collapsedWidthClass: "lg:w-(--sidebar-collapsed-width)",
				autoHover: false,
				hoverRequiresActive: false
			},
			condensed: {
				desktopMode: "persistent",
				expandedWidthClass: "lg:w-[15rem]",
				collapsedWidthClass: "lg:w-[4.5rem]",
				autoHover: false,
				hoverRequiresActive: false
			},
			hidden: {
				desktopMode: "drawer",
				expandedWidthClass: "lg:w-(--sidebar-width)",
				collapsedWidthClass: "lg:w-(--sidebar-collapsed-width)",
				autoHover: false,
				hoverRequiresActive: false
			},
			small_hover: {
				desktopMode: "persistent",
				expandedWidthClass: "lg:w-(--sidebar-width)",
				collapsedWidthClass: "lg:w-(--sidebar-collapsed-width)",
				autoHover: true,
				hoverRequiresActive: false
			},
			small_hover_active: {
				desktopMode: "persistent",
				expandedWidthClass: "lg:w-(--sidebar-width)",
				collapsedWidthClass: "lg:w-(--sidebar-collapsed-width)",
				autoHover: true,
				hoverRequiresActive: true
			}
		};
		const props = __props;
		const emit = __emit;
		const page = usePage();
		const { t } = useI18n();
		const { settings } = useSystemSettings();
		const { mode } = useThemeMode();
		const currentPath = computed(() => page.url.split("?")[0]);
		const appName = computed(() => settings.value.brandName || page.props.app?.name || t("common.app"));
		const logoUrl = computed(() => {
			if (mode.value === "dark") return settings.value.logoDarkUrl ?? settings.value.logoLightUrl ?? settings.value.logoUrl ?? "";
			return settings.value.logoLightUrl ?? settings.value.logoDarkUrl ?? settings.value.logoUrl ?? "";
		});
		const iconUrl = computed(() => settings.value.iconUrl ?? "");
		const hoverToggleIcon = computed(() => {
			const isRtl = settings.value.direction === "rtl";
			if (isHoverPinnedOpen.value) return isRtl ? "pi pi-angle-double-left" : "pi pi-angle-double-right";
			return isRtl ? "pi pi-angle-double-right" : "pi pi-angle-double-left";
		});
		const groupState = ref({});
		const isHoverExpanded = ref(false);
		const isHoverPinnedOpen = ref(false);
		const isDesktop = ref(false);
		const syncViewport = () => {
			if (typeof window === "undefined") return;
			isDesktop.value = window.innerWidth >= 1024;
		};
		const behaviorConfig = computed(() => SIDEBAR_BEHAVIOR_CONFIG[props.behavior] ?? SIDEBAR_BEHAVIOR_CONFIG.default);
		const isAutoHoverBehavior = computed(() => behaviorConfig.value.autoHover);
		const isHiddenBehavior = computed(() => behaviorConfig.value.desktopMode === "drawer");
		const activeNavExists = computed(() => groupedItems.value.some((group) => groupHasActive(group)));
		const expandedWidthClass = computed(() => behaviorConfig.value.expandedWidthClass);
		const collapsedWidthClass = computed(() => behaviorConfig.value.collapsedWidthClass);
		const effectiveCollapsed = computed(() => {
			if (!isDesktop.value) return false;
			if (isHiddenBehavior.value) return !props.mobileOpen;
			if (isAutoHoverBehavior.value) {
				if (isHoverPinnedOpen.value) return false;
				if (behaviorConfig.value.hoverRequiresActive && !activeNavExists.value) return true;
				return !isHoverExpanded.value;
			}
			return props.collapsed;
		});
		const groupedItems = computed(() => {
			const sourceItems = props.items ?? [];
			if (!Array.isArray(sourceItems) || sourceItems.length === 0) return [];
			if (sourceItems.every((entry) => Array.isArray(entry?.items))) return sourceItems.map((entry, index) => ({
				id: entry.id ?? entry.groupId ?? entry.group ?? entry.groupKey ?? `group-${index}`,
				label: entry.labelKey ? t(entry.labelKey) : entry.label ?? "Navigation",
				collapsible: true,
				items: (entry.items ?? []).map((item) => ({
					...item,
					label: item.labelKey ? t(item.labelKey) : item.label
				}))
			}));
			const grouped = [];
			const groupIndex = /* @__PURE__ */ new Map();
			sourceItems.forEach((item) => {
				if (!item.group && !item.groupKey) {
					grouped.push({
						id: `single:${item.href}`,
						label: "",
						collapsible: false,
						items: [{
							...item,
							label: item.labelKey ? t(item.labelKey) : item.label
						}]
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
						items: []
					});
				}
				grouped[groupIndex.get(groupId)].items.push({
					...item,
					label: item.labelKey ? t(item.labelKey) : item.label
				});
			});
			return grouped;
		});
		const isActive = (href) => currentPath.value === href || currentPath.value.startsWith(`${href}/`);
		const groupHasActive = (group) => group.items.some((item) => isActive(item.href));
		watchEffect(() => {
			const next = { ...groupState.value };
			groupedItems.value.forEach((group) => {
				if (!group.collapsible || Object.prototype.hasOwnProperty.call(next, group.id)) return;
				next[group.id] = groupHasActive(group);
			});
			groupState.value = next;
		});
		onMounted(() => {
			if (typeof window === "undefined") return;
			syncViewport();
			window.addEventListener("resize", syncViewport);
			const raw = window.localStorage.getItem(SIDEBAR_GROUP_STATE_KEY);
			if (raw) try {
				const parsed = JSON.parse(raw);
				if (parsed && typeof parsed === "object" && !Array.isArray(parsed)) groupState.value = parsed;
			} catch {}
			isHoverPinnedOpen.value = window.localStorage.getItem(SIDEBAR_HOVER_PIN_STATE_KEY) === "true";
		});
		onBeforeUnmount(() => {
			if (typeof window === "undefined") return;
			window.removeEventListener("resize", syncViewport);
		});
		watch(groupState, (next) => {
			if (typeof window === "undefined") return;
			window.localStorage.setItem(SIDEBAR_GROUP_STATE_KEY, JSON.stringify(next));
		}, { deep: true });
		watch(() => props.collapsed, (next) => {
			if (!next) isHoverExpanded.value = false;
		});
		watch(isHoverPinnedOpen, (next) => {
			if (typeof window === "undefined") return;
			window.localStorage.setItem(SIDEBAR_HOVER_PIN_STATE_KEY, String(next));
		});
		watch(() => props.behavior, (behavior) => {
			if (!HOVER_BEHAVIORS.has(behavior)) {
				isHoverPinnedOpen.value = false;
				isHoverExpanded.value = false;
			}
		});
		const mobileClosedClass = computed(() => settings.value.direction === "rtl" ? "translate-x-full lg:translate-x-0" : "-translate-x-full lg:translate-x-0");
		const drawerClosedClass = computed(() => settings.value.direction === "rtl" ? "translate-x-full" : "-translate-x-full");
		const asideBaseClass = computed(() => isHiddenBehavior.value ? "fixed inset-y-0 z-40 w-(--sidebar-width) shrink-0 overflow-hidden bg-(--card) [inset-inline-start:0] [border-inline-end:1px_solid_var(--border)] h-screen transition-[transform,width] duration-300 ease-[cubic-bezier(0.22,1,0.36,1)]" : "fixed inset-y-0 z-40 w-(--sidebar-width) shrink-0 overflow-hidden bg-(--card) [inset-inline-start:0] [border-inline-end:1px_solid_var(--border)] lg:sticky lg:top-0 lg:z-30 lg:h-screen transition-[transform,width] duration-300 ease-[cubic-bezier(0.22,1,0.36,1)]");
		const overlayVisible = computed(() => props.mobileOpen);
		const overlayClass = computed(() => isHiddenBehavior.value ? "fixed inset-0 z-30 bg-black/40" : "fixed inset-0 z-30 bg-black/40 lg:hidden");
		const translateClass = computed(() => {
			if (isHiddenBehavior.value) return props.mobileOpen ? "translate-x-0" : drawerClosedClass.value;
			return props.mobileOpen ? "translate-x-0" : mobileClosedClass.value;
		});
		return (_ctx, _push, _parent, _attrs) => {
			_push(`<!--[-->`);
			if (overlayVisible.value) _push(`<div class="${ssrRenderClass(overlayClass.value)}"></div>`);
			else _push(`<!---->`);
			_push(`<aside class="${ssrRenderClass([
				asideBaseClass.value,
				translateClass.value,
				effectiveCollapsed.value ? collapsedWidthClass.value : expandedWidthClass.value
			])}"><div class="flex h-full flex-col"><div class="relative shrink-0 p-5">`);
			if (isAutoHoverBehavior.value && !effectiveCollapsed.value && !__props.mobileOpen) _push(`<button type="button" class="absolute top-3 inline-flex h-9 w-9 items-center justify-center rounded-sm text-(--muted-foreground) transition hover:bg-[color-mix(in_oklab,var(--accent)_12%,transparent)] hover:text-(--foreground) inset-e-3"${ssrRenderAttr("title", isHoverPinnedOpen.value ? unref(t)("topbar.collapseSidebar") : unref(t)("topbar.expandSidebar"))}${ssrRenderAttr("aria-label", isHoverPinnedOpen.value ? unref(t)("topbar.collapseSidebar") : unref(t)("topbar.expandSidebar"))}><i class="${ssrRenderClass(hoverToggleIcon.value)}"></i></button>`);
			else _push(`<!---->`);
			if (__props.mobileOpen) _push(`<button type="button" class="absolute top-3 inline-flex h-9 w-9 items-center justify-center rounded-sm text-(--muted-foreground) transition hover:bg-[color-mix(in_oklab,var(--accent)_12%,transparent)] hover:text-(--foreground) inset-e-3"${ssrRenderAttr("title", unref(t)("common.close"))}${ssrRenderAttr("aria-label", unref(t)("common.close"))}><i class="pi pi-times"></i></button>`);
			else _push(`<!---->`);
			_push(`<div class="${ssrRenderClass([effectiveCollapsed.value ? "lg:justify-center" : "", "flex items-center gap-3"])}">`);
			if (effectiveCollapsed.value) {
				_push(`<div class="lg:flex lg:items-center lg:justify-center">`);
				if (iconUrl.value) _push(`<img${ssrRenderAttr("src", iconUrl.value)}${ssrRenderAttr("alt", unref(t)("settings.currentAppIcon"))} class="h-10 w-10 object-contain">`);
				else _push(`<!---->`);
				_push(`</div>`);
			} else _push(`<!---->`);
			_push(`<div class="${ssrRenderClass(effectiveCollapsed.value ? "lg:hidden" : "")}">`);
			if (logoUrl.value) _push(`<img${ssrRenderAttr("src", logoUrl.value)}${ssrRenderAttr("alt", `${appName.value} logo`)} class="h-9 w-auto max-w-44 object-contain">`);
			else _push(`<p class="text-2xl font-bold leading-tight">${ssrInterpolate(appName.value)}</p>`);
			_push(`</div></div></div><nav class="min-h-0 flex-1 space-y-4 overflow-y-auto px-3 pb-4"><!--[-->`);
			ssrRenderList(groupedItems.value, (group) => {
				_push(`<section class="space-y-1">`);
				if (!effectiveCollapsed.value && group.collapsible) _push(`<button type="button" class="flex w-full items-center justify-between rounded-sm px-2 py-2 text-sm font-semibold tracking-wide text-(--muted-foreground) uppercase transition-colors duration-200 hover:bg-[color-mix(in_oklab,var(--accent)_8%,transparent)]"><span>${ssrInterpolate(group.label)}</span><i class="${ssrRenderClass(groupState.value[group.id] ? "pi pi-angle-down" : "pi pi-angle-right")}"></i></button>`);
				else _push(`<!---->`);
				if (effectiveCollapsed.value || !group.collapsible || groupState.value[group.id]) {
					_push(`<div class="${ssrRenderClass([effectiveCollapsed.value || !group.collapsible ? "" : "before:absolute before:inset-y-2 before:inset-s-[0.85rem] before:w-px before:bg-[color-mix(in_oklab,var(--border)_88%,transparent)]", "relative space-y-1"])}"><!--[-->`);
					ssrRenderList(group.items, (item) => {
						_push(ssrRenderComponent(unref(Link), {
							key: item.href,
							href: item.href,
							class: ["relative flex items-center gap-3 rounded-[calc(var(--radius-base)-0.25rem)] py-3.5 text-base font-medium transition-all duration-200", [effectiveCollapsed.value ? "justify-center px-2" : group.collapsible ? "ps-7 pe-3" : "px-3", isActive(item.href) ? "bg-(--accent) text-(--accent-contrast) shadow-[inset_0_0_0_1px_color-mix(in_oklab,var(--accent)_65%,white)]" : "text-(--muted-foreground) hover:bg-[color-mix(in_oklab,var(--accent)_12%,transparent)] hover:text-(--foreground)"]],
							title: item.label,
							onClick: ($event) => emit("closeMobile")
						}, {
							default: withCtx((_, _push, _parent, _scopeId) => {
								if (_push) _push(`<i class="${ssrRenderClass([item.icon ?? "pi pi-circle", "text-sm"])}"${_scopeId}></i><span class="${ssrRenderClass(["transition-opacity duration-200", effectiveCollapsed.value ? "hidden opacity-0" : "opacity-100"])}"${_scopeId}>${ssrInterpolate(item.label)}</span>`);
								else return [createVNode("i", { class: [item.icon ?? "pi pi-circle", "text-sm"] }, null, 2), createVNode("span", { class: ["transition-opacity duration-200", effectiveCollapsed.value ? "hidden opacity-0" : "opacity-100"] }, toDisplayString(item.label), 3)];
							}),
							_: 2
						}, _parent));
					});
					_push(`<!--]--></div>`);
				} else _push(`<!---->`);
				_push(`</section>`);
			});
			_push(`<!--]--></nav></div></aside><!--]-->`);
		};
	}
};
var _sfc_setup$2 = _sfc_main$2.setup;
_sfc_main$2.setup = (props, ctx) => {
	const ssrContext = useSSRContext();
	(ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/components/admin/AdminSidebar.vue");
	return _sfc_setup$2 ? _sfc_setup$2(props, ctx) : void 0;
};
//#endregion
//#region resources/js/components/admin/AdminTopbar.vue
var _sfc_main$1 = {
	__name: "AdminTopbar",
	__ssrInlineRender: true,
	props: {
		collapsed: {
			type: Boolean,
			default: false
		},
		title: {
			type: String,
			default: ""
		},
		behavior: {
			type: String,
			default: "default"
		}
	},
	emits: ["toggleSidebar", "toggleMobileSidebar"],
	setup(__props, { emit: __emit }) {
		const props = __props;
		const page = usePage();
		const { t } = useI18n();
		const userName = computed(() => page.props.auth?.user?.name ?? t("topbar.admin"));
		const userEmail = computed(() => page.props.auth?.user?.email ?? "");
		const userInitials = computed(() => {
			return userName.value.trim().split(/\s+/).filter(Boolean).slice(0, 2).map((part) => part[0]).join("").toUpperCase() || "AD";
		});
		const logoutForm = useForm({});
		const profileMenu = ref();
		const { mode, toggleMode } = useThemeMode();
		const { settings, setLanguage, setDirection } = useSystemSettings();
		const themeIcon = computed(() => mode.value === "dark" ? "pi pi-sun" : "pi pi-moon");
		const themeLabel = computed(() => mode.value === "dark" ? t("topbar.lightMode") : t("topbar.darkMode"));
		const isFullscreen = ref(false);
		const nextLanguage = computed(() => settings.value.language === "ar" ? "en" : "ar");
		const languageToggleLabel = computed(() => nextLanguage.value === "ar" ? "AR" : "EN");
		const languageToggleTitle = computed(() => nextLanguage.value === "ar" ? t("settings.arabic") : t("settings.english"));
		const sidebarIcon = computed(() => {
			if (props.behavior === "condensed" && props.collapsed) return "pi pi-bars";
			const isRtl = settings.value.direction === "rtl";
			if (props.collapsed) return isRtl ? "pi pi-angle-double-left" : "pi pi-angle-double-right";
			return isRtl ? "pi pi-angle-double-right" : "pi pi-angle-double-left";
		});
		const isHiddenSidebarBehavior = computed(() => props.behavior === "hidden");
		const canToggleDesktopSidebar = computed(() => props.behavior === "condensed");
		const syncFullscreenState = () => {
			if (typeof document === "undefined") return;
			isFullscreen.value = Boolean(document.fullscreenElement);
		};
		const fullscreenIcon = computed(() => isFullscreen.value ? "pi pi-window-minimize" : "pi pi-window-maximize");
		const fullscreenLabel = computed(() => isFullscreen.value ? t("topbar.exitFullscreen") : t("topbar.enterFullscreen"));
		const profileMenuItems = computed(() => [{
			label: t("profile.updatePassword"),
			icon: "pi pi-key",
			command: () => router.get("/admin/password")
		}, {
			label: t("topbar.logout"),
			icon: "pi pi-sign-out",
			severity: "danger",
			command: () => logoutForm.post("/admin/logout")
		}]);
		onMounted(() => {
			syncFullscreenState();
			document.addEventListener("fullscreenchange", syncFullscreenState);
		});
		onBeforeUnmount(() => {
			document.removeEventListener("fullscreenchange", syncFullscreenState);
		});
		return (_ctx, _push, _parent, _attrs) => {
			const _directive_ripple = resolveDirective("ripple");
			_push(`<header${ssrRenderAttrs(mergeProps({ class: "sticky top-0 z-20 border-b border-(--border) bg-(--card)/95 px-4 py-3 text-(--card-foreground) backdrop-blur sm:px-6" }, _attrs))}><div class="flex min-h-(--topbar-height) items-center justify-between gap-3"><div class="flex min-w-0 items-center gap-2"><button type="button" class="${ssrRenderClass([isHiddenSidebarBehavior.value ? "" : "lg:hidden", "inline-flex h-11 w-11 items-center justify-center rounded-md bg-transparent text-base text-(--foreground) transition hover:bg-[color-mix(in_oklab,var(--accent)_12%,transparent)]"])}"${ssrRenderAttr("aria-label", unref(t)("topbar.openSidebar"))}${ssrRenderAttr("title", unref(t)("topbar.openSidebar"))}><i class="pi pi-bars"></i></button>`);
			if (canToggleDesktopSidebar.value) _push(`<button type="button" class="hidden h-11 w-11 items-center justify-center rounded-md bg-transparent text-base text-(--foreground) transition hover:bg-[color-mix(in_oklab,var(--accent)_12%,transparent)] lg:inline-flex"${ssrRenderAttr("aria-label", props.collapsed ? unref(t)("topbar.expandSidebar") : unref(t)("topbar.collapseSidebar"))}${ssrRenderAttr("title", props.collapsed ? unref(t)("topbar.expandSidebar") : unref(t)("topbar.collapseSidebar"))}><i class="${ssrRenderClass(sidebarIcon.value)}"></i></button>`);
			else _push(`<!---->`);
			_push(`<div class="min-w-0"><h1 class="truncate text-2xl font-semibold">${ssrInterpolate(props.title)}</h1></div></div><div class="flex items-center gap-2 sm:gap-3"><button type="button" class="inline-flex h-11 items-center gap-2 rounded-md bg-(--background) px-3 text-sm font-semibold text-(--foreground) transition hover:bg-[color-mix(in_oklab,var(--accent)_12%,transparent)]"${ssrRenderAttr("aria-label", languageToggleTitle.value)}${ssrRenderAttr("title", languageToggleTitle.value)}><i class="pi pi-language text-sm text-(--muted-foreground)"></i><span>${ssrInterpolate(languageToggleLabel.value)}</span></button><button type="button" class="inline-flex h-11 w-11 items-center justify-center rounded-md bg-transparent text-base text-(--foreground) transition hover:bg-[color-mix(in_oklab,var(--accent)_12%,transparent)]"${ssrRenderAttr("aria-label", themeLabel.value)}${ssrRenderAttr("title", themeLabel.value)}><i class="${ssrRenderClass(themeIcon.value)}"></i></button><button type="button" class="hidden h-11 w-11 items-center justify-center rounded-md bg-transparent text-base text-(--foreground) transition hover:bg-[color-mix(in_oklab,var(--accent)_12%,transparent)] lg:inline-flex"${ssrRenderAttr("aria-label", fullscreenLabel.value)}${ssrRenderAttr("title", fullscreenLabel.value)}><i class="${ssrRenderClass(fullscreenIcon.value)}"></i></button><button type="button" class="rounded-full border-0 bg-transparent p-0 hover:bg-transparent"${ssrRenderAttr("aria-label", unref(t)("topbar.openProfileMenu"))}><div class="flex h-10 w-10 items-center justify-center rounded-full bg-(--accent) text-sm font-semibold text-(--accent-contrast)">${ssrInterpolate(userInitials.value)}</div></button>`);
			_push(ssrRenderComponent(unref(Menu), {
				ref_key: "profileMenu",
				ref: profileMenu,
				model: profileMenuItems.value,
				popup: ""
			}, {
				start: withCtx((_, _push, _parent, _scopeId) => {
					if (_push) _push(`<div class="border-b border-(--border) px-4 py-3"${_scopeId}><p class="text-sm font-semibold"${_scopeId}>${ssrInterpolate(userName.value)}</p><p class="text-xs text-(--muted-foreground)"${_scopeId}>${ssrInterpolate(userEmail.value)}</p></div>`);
					else return [createVNode("div", { class: "border-b border-(--border) px-4 py-3" }, [createVNode("p", { class: "text-sm font-semibold" }, toDisplayString(userName.value), 1), createVNode("p", { class: "text-xs text-(--muted-foreground)" }, toDisplayString(userEmail.value), 1)])];
				}),
				item: withCtx(({ item, props: itemProps }, _push, _parent, _scopeId) => {
					if (_push) _push(`<a${ssrRenderAttrs(mergeProps(itemProps.action, { class: ["flex items-center gap-2 rounded-sm px-3 py-2 text-base font-medium transition-colors", item.severity === "danger" ? "text-(--foreground) hover:bg-rose-50 hover:text-rose-600 dark:hover:bg-rose-900/30 dark:hover:text-rose-300" : "text-(--foreground) hover:bg-[color-mix(in_oklab,var(--accent)_12%,transparent)]"] }, ssrGetDirectiveProps(_ctx, _directive_ripple)))}${_scopeId}><i class="${ssrRenderClass([item.icon, "text-base text-current"])}"${_scopeId}></i><span class="text-current"${_scopeId}>${ssrInterpolate(item.label)}</span></a>`);
					else return [withDirectives((openBlock(), createBlock("a", mergeProps(itemProps.action, { class: ["flex items-center gap-2 rounded-sm px-3 py-2 text-base font-medium transition-colors", item.severity === "danger" ? "text-(--foreground) hover:bg-rose-50 hover:text-rose-600 dark:hover:bg-rose-900/30 dark:hover:text-rose-300" : "text-(--foreground) hover:bg-[color-mix(in_oklab,var(--accent)_12%,transparent)]"] }), [createVNode("i", { class: [item.icon, "text-base text-current"] }, null, 2), createVNode("span", { class: "text-current" }, toDisplayString(item.label), 1)], 16)), [[_directive_ripple]])];
				}),
				_: 1
			}, _parent));
			_push(`</div></div></header>`);
		};
	}
};
var _sfc_setup$1 = _sfc_main$1.setup;
_sfc_main$1.setup = (props, ctx) => {
	const ssrContext = useSSRContext();
	(ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/components/admin/AdminTopbar.vue");
	return _sfc_setup$1 ? _sfc_setup$1(props, ctx) : void 0;
};
//#endregion
//#region resources/js/components/admin/AdminLayout.vue
var SIDEBAR_STATE_KEY = "vita_admin_sidebar_collapsed";
var _sfc_main = {
	__name: "AdminLayout",
	__ssrInlineRender: true,
	props: {
		pageTitle: {
			type: String,
			default: "Dashboard"
		},
		navItems: {
			type: Array,
			default: () => []
		}
	},
	setup(__props) {
		const props = __props;
		const isMobileSidebarOpen = ref(false);
		const page = usePage();
		const appToast = useAppToast();
		const { settings } = useSystemSettings();
		const showFlash = (flash) => {
			const success = flash?.success ?? "";
			const error = flash?.error ?? "";
			if (success) appToast.success(success);
			if (error) appToast.error(error);
		};
		const filteredNavItems = computed(() => {
			const user = page.props.auth?.user;
			return filterNavItemsByAccess(props.navItems, {
				roles: user?.roles ?? [],
				permissions: user?.permissions ?? []
			});
		});
		const sidebarBehavior = computed(() => settings.value.sidebarBehavior ?? "default");
		const isAutoCollapsedBehavior = computed(() => [
			"hidden",
			"small_hover_active",
			"small_hover"
		].includes(sidebarBehavior.value));
		const isSidebarCollapsed = computed(() => {
			if (sidebarBehavior.value === "default") return false;
			if (sidebarBehavior.value === "condensed") return isSidebarUserCollapsed.value;
			return true;
		});
		const isSidebarUserCollapsed = ref(true);
		const toggleSidebar = () => {
			if (isAutoCollapsedBehavior.value || sidebarBehavior.value === "default") return;
			isSidebarUserCollapsed.value = !isSidebarUserCollapsed.value;
			if (typeof window !== "undefined") window.localStorage.setItem(SIDEBAR_STATE_KEY, String(isSidebarUserCollapsed.value));
		};
		const toggleMobileSidebar = () => {
			isMobileSidebarOpen.value = !isMobileSidebarOpen.value;
		};
		const closeMobileSidebar = () => {
			isMobileSidebarOpen.value = false;
		};
		const handleResize = () => {
			if (typeof window !== "undefined" && window.innerWidth >= 1024) isMobileSidebarOpen.value = false;
		};
		onMounted(() => {
			if (typeof window !== "undefined") {
				const saved = window.localStorage.getItem(SIDEBAR_STATE_KEY);
				isSidebarUserCollapsed.value = saved === null ? false : saved === "true";
			}
			window.addEventListener("resize", handleResize);
			showFlash(page.props.flash);
		});
		watch(sidebarBehavior, (behavior) => {
			if (behavior === "default") return;
			isSidebarUserCollapsed.value = true;
		}, { immediate: true });
		onBeforeUnmount(() => {
			window.removeEventListener("resize", handleResize);
		});
		watch(() => page.props.flash, (flash) => {
			showFlash(flash);
		}, { deep: true });
		return (_ctx, _push, _parent, _attrs) => {
			_push(`<main${ssrRenderAttrs(mergeProps({ class: "min-h-screen bg-(--background) text-(--foreground) transition-colors" }, _attrs))}>`);
			_push(ssrRenderComponent(unref(Toast), { position: "bottom-right" }, null, _parent));
			_push(`<div class="flex min-h-screen w-full flex-col lg:flex-row">`);
			_push(ssrRenderComponent(_sfc_main$2, {
				items: filteredNavItems.value,
				collapsed: isSidebarCollapsed.value,
				behavior: sidebarBehavior.value,
				"mobile-open": isMobileSidebarOpen.value,
				onCloseMobile: closeMobileSidebar
			}, null, _parent));
			_push(`<section class="min-w-0 flex-1">`);
			_push(ssrRenderComponent(_sfc_main$1, {
				title: __props.pageTitle,
				collapsed: isSidebarCollapsed.value,
				behavior: sidebarBehavior.value,
				onToggleSidebar: toggleSidebar,
				onToggleMobileSidebar: toggleMobileSidebar
			}, null, _parent));
			_push(`<div class="p-4 sm:p-6">`);
			ssrRenderSlot(_ctx.$slots, "default", {}, null, _push, _parent);
			_push(`</div></section></div></main>`);
		};
	}
};
var _sfc_setup = _sfc_main.setup;
_sfc_main.setup = (props, ctx) => {
	const ssrContext = useSSRContext();
	(ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/components/admin/AdminLayout.vue");
	return _sfc_setup ? _sfc_setup(props, ctx) : void 0;
};
//#endregion
export { adminNavItems as n, _sfc_main as t };
