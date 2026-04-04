import { computed, createTextVNode, mergeProps, toDisplayString, unref, useSSRContext, withCtx } from "vue";
import { ssrInterpolate, ssrRenderAttrs, ssrRenderComponent, ssrRenderList } from "vue/server-renderer";
import { Link, usePage } from "@inertiajs/vue3";
import { useI18n } from "vue-i18n";
//#region resources/js/components/admin/AdminBreadcrumbs.vue
var _sfc_main = {
	__name: "AdminBreadcrumbs",
	__ssrInlineRender: true,
	props: { items: {
		type: Array,
		default: () => []
	} },
	setup(__props) {
		const props = __props;
		const page = usePage();
		const { t } = useI18n();
		const labelKeyBySegment = {
			admin: "breadcrumbs.admin",
			dashboard: "breadcrumbs.dashboard",
			whatsapp: "breadcrumbs.whatsapp",
			users: "breadcrumbs.users",
			roles: "breadcrumbs.roles",
			plans: "breadcrumbs.plans",
			"activity-logs": "breadcrumbs.activityLogs",
			settings: "breadcrumbs.settings",
			password: "breadcrumbs.password",
			create: "breadcrumbs.create",
			edit: "breadcrumbs.edit"
		};
		const toTitle = (value) => value.replace(/[-_]/g, " ").replace(/\b\w/g, (char) => char.toUpperCase());
		const derivedItems = computed(() => {
			if (props.items.length > 0) return props.items.map((item) => ({
				...item,
				label: item.labelKey ? t(item.labelKey) : item.label
			}));
			const cleanPath = page.url.split("?")[0];
			const segments = cleanPath.split("/").filter(Boolean);
			const items = [];
			let href = "";
			let hasDashboard = false;
			for (let index = 0; index < segments.length; index += 1) {
				const segment = segments[index];
				const nextSegment = segments[index + 1] ?? "";
				const isIdSegment = /^\d+$/.test(segment);
				if (segment === "admin") {
					href += "/admin";
					continue;
				}
				if (segment === "dashboard") hasDashboard = true;
				if (isIdSegment && nextSegment === "edit") continue;
				href += "/" + segment;
				const labelKey = labelKeyBySegment[segment];
				items.push({
					label: labelKey ? t(labelKey) : toTitle(segment),
					href
				});
			}
			if (!hasDashboard && cleanPath.startsWith("/admin")) return [{
				label: t("breadcrumbs.dashboard"),
				href: "/admin/dashboard"
			}, ...items];
			return items;
		});
		return (_ctx, _push, _parent, _attrs) => {
			_push(`<nav${ssrRenderAttrs(mergeProps({ "aria-label": unref(t)("breadcrumbs.ariaLabel") }, _attrs))}><ol class="flex flex-wrap items-center gap-2 text-sm"><!--[-->`);
			ssrRenderList(derivedItems.value, (item, index) => {
				_push(`<li class="flex items-center gap-2">`);
				if (index > 0) _push(`<span class="text-(--muted-foreground)">/</span>`);
				else _push(`<!---->`);
				if (index < derivedItems.value.length - 1) _push(ssrRenderComponent(unref(Link), {
					href: item.href,
					class: "text-(--muted-foreground) hover:text-(--foreground)"
				}, {
					default: withCtx((_, _push, _parent, _scopeId) => {
						if (_push) _push(`${ssrInterpolate(item.label)}`);
						else return [createTextVNode(toDisplayString(item.label), 1)];
					}),
					_: 2
				}, _parent));
				else _push(`<span class="font-medium text-(--foreground)">${ssrInterpolate(item.label)}</span>`);
				_push(`</li>`);
			});
			_push(`<!--]--></ol></nav>`);
		};
	}
};
var _sfc_setup = _sfc_main.setup;
_sfc_main.setup = (props, ctx) => {
	const ssrContext = useSSRContext();
	(ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/components/admin/AdminBreadcrumbs.vue");
	return _sfc_setup ? _sfc_setup(props, ctx) : void 0;
};
//#endregion
export { _sfc_main as t };
