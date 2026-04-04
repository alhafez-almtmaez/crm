import { computed, unref, useSSRContext } from "vue";
import { ssrInterpolate, ssrRenderComponent, ssrRenderStyle } from "vue/server-renderer";
import { Head, router, usePage } from "@inertiajs/vue3";
import Button from "primevue/button";
//#region resources/js/Pages/Error.vue
var dashboardUrl = "/admin/dashboard";
var homeUrl = "/";
var _sfc_main = {
	__name: "Error",
	__ssrInlineRender: true,
	props: { status: {
		type: Number,
		required: true
	} },
	setup(__props) {
		const props = __props;
		const page = usePage();
		const isAuthenticated = computed(() => Boolean(page.props.auth?.user));
		const contentByStatus = {
			403: { key: "forbidden" },
			404: { key: "notFound" },
			419: { key: "expired" },
			500: { key: "serverError" },
			503: { key: "unavailable" }
		};
		const statusContent = computed(() => contentByStatus[props.status] ?? contentByStatus[500]);
		return (_ctx, _push, _parent, _attrs) => {
			_push(`<!--[-->`);
			_push(ssrRenderComponent(unref(Head), { title: `${__props.status} • ${_ctx.$t(`errors.${statusContent.value.key}.title`)}` }, null, _parent));
			_push(`<div class="flex min-h-screen items-center justify-center bg-(--background) px-6 py-10 text-(--foreground)"><section class="w-full max-w-4xl text-center"><p class="text-8xl font-black leading-none md:text-9xl" style="${ssrRenderStyle({ color: "var(--accent)" })}">${ssrInterpolate(__props.status)}</p><h1 class="mt-6 text-3xl font-bold md:text-5xl">${ssrInterpolate(_ctx.$t(`errors.${statusContent.value.key}.title`))}</h1><p class="mx-auto mt-4 max-w-2xl text-lg text-(--muted-foreground)">${ssrInterpolate(_ctx.$t(`errors.${statusContent.value.key}.description`))}</p><div class="mt-10 flex flex-wrap items-center justify-center gap-3">`);
			if (isAuthenticated.value) _push(ssrRenderComponent(unref(Button), {
				icon: "pi pi-home",
				label: _ctx.$t("errors.actions.backToDashboard"),
				onClick: ($event) => unref(router).visit(dashboardUrl)
			}, null, _parent));
			else _push(ssrRenderComponent(unref(Button), {
				icon: "pi pi-home",
				label: _ctx.$t("errors.actions.backToHome"),
				onClick: ($event) => unref(router).visit(homeUrl)
			}, null, _parent));
			_push(`</div></section></div><!--]-->`);
		};
	}
};
var _sfc_setup = _sfc_main.setup;
_sfc_main.setup = (props, ctx) => {
	const ssrContext = useSSRContext();
	(ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Pages/Error.vue");
	return _sfc_setup ? _sfc_setup(props, ctx) : void 0;
};
//#endregion
export { _sfc_main as default };
