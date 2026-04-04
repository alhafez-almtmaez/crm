import { n as adminNavItems, t as _sfc_main$1 } from "./AdminLayout-XMnZ2yke.js";
import { Fragment, computed, createBlock, createVNode, openBlock, renderList, toDisplayString, unref, useSSRContext, withCtx } from "vue";
import { ssrInterpolate, ssrRenderComponent, ssrRenderList } from "vue/server-renderer";
import { Head } from "@inertiajs/vue3";
import { useI18n } from "vue-i18n";
//#region resources/js/Pages/Admin/Dashboard.vue
var _sfc_main = {
	__name: "Dashboard",
	__ssrInlineRender: true,
	setup(__props) {
		const { t } = useI18n();
		const stats = computed(() => [
			{
				label: t("dashboard.orders"),
				value: "0"
			},
			{
				label: t("dashboard.products"),
				value: "0"
			},
			{
				label: t("dashboard.customers"),
				value: "0"
			},
			{
				label: t("dashboard.revenue"),
				value: "$0.00"
			}
		]);
		return (_ctx, _push, _parent, _attrs) => {
			_push(`<!--[-->`);
			_push(ssrRenderComponent(unref(Head), { title: unref(t)("dashboard.title") }, null, _parent));
			_push(ssrRenderComponent(_sfc_main$1, {
				"nav-items": unref(adminNavItems),
				"page-title": unref(t)("dashboard.title")
			}, {
				default: withCtx((_, _push, _parent, _scopeId) => {
					if (_push) {
						_push(`<section class="space-y-6"${_scopeId}><header${_scopeId}><h2 class="text-4xl font-semibold tracking-tight"${_scopeId}>${ssrInterpolate(unref(t)("dashboard.title"))}</h2><p class="mt-3 text-lg text-(--muted-foreground)"${_scopeId}>${ssrInterpolate(unref(t)("dashboard.subtitle"))}</p></header><article class="rounded-(--radius-base) border border-(--border) bg-(--card) p-6 text-(--card-foreground) shadow-(--shadow-sm)"${_scopeId}><h3 class="text-3xl font-semibold"${_scopeId}>${ssrInterpolate(unref(t)("dashboard.overview"))}</h3><p class="mt-2 text-lg text-(--muted-foreground)"${_scopeId}>${ssrInterpolate(unref(t)("dashboard.description"))}</p></article><section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4"${_scopeId}><!--[-->`);
						ssrRenderList(stats.value, (item) => {
							_push(`<article class="rounded-(--radius-base) border border-(--border) bg-(--card) p-4 text-(--card-foreground) shadow-(--shadow-sm)"${_scopeId}><p class="text-xs uppercase text-(--muted-foreground)"${_scopeId}>${ssrInterpolate(item.label)}</p><p class="mt-2 text-2xl font-semibold"${_scopeId}>${ssrInterpolate(item.value)}</p></article>`);
						});
						_push(`<!--]--></section></section>`);
					} else return [createVNode("section", { class: "space-y-6" }, [
						createVNode("header", null, [createVNode("h2", { class: "text-4xl font-semibold tracking-tight" }, toDisplayString(unref(t)("dashboard.title")), 1), createVNode("p", { class: "mt-3 text-lg text-(--muted-foreground)" }, toDisplayString(unref(t)("dashboard.subtitle")), 1)]),
						createVNode("article", { class: "rounded-(--radius-base) border border-(--border) bg-(--card) p-6 text-(--card-foreground) shadow-(--shadow-sm)" }, [createVNode("h3", { class: "text-3xl font-semibold" }, toDisplayString(unref(t)("dashboard.overview")), 1), createVNode("p", { class: "mt-2 text-lg text-(--muted-foreground)" }, toDisplayString(unref(t)("dashboard.description")), 1)]),
						createVNode("section", { class: "grid gap-4 sm:grid-cols-2 xl:grid-cols-4" }, [(openBlock(true), createBlock(Fragment, null, renderList(stats.value, (item) => {
							return openBlock(), createBlock("article", {
								key: item.label,
								class: "rounded-(--radius-base) border border-(--border) bg-(--card) p-4 text-(--card-foreground) shadow-(--shadow-sm)"
							}, [createVNode("p", { class: "text-xs uppercase text-(--muted-foreground)" }, toDisplayString(item.label), 1), createVNode("p", { class: "mt-2 text-2xl font-semibold" }, toDisplayString(item.value), 1)]);
						}), 128))])
					])];
				}),
				_: 1
			}, _parent));
			_push(`<!--]-->`);
		};
	}
};
var _sfc_setup = _sfc_main.setup;
_sfc_main.setup = (props, ctx) => {
	const ssrContext = useSSRContext();
	(ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Pages/Admin/Dashboard.vue");
	return _sfc_setup ? _sfc_setup(props, ctx) : void 0;
};
//#endregion
export { _sfc_main as default };
