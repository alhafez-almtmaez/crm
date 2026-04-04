import { n as adminNavItems, t as _sfc_main$1 } from "./AdminLayout-XMnZ2yke.js";
import { t as _sfc_main$2 } from "./AdminBreadcrumbs-Ddp11j0e.js";
import { t as _sfc_main$3 } from "./PlanFormCard-9Ngf-CFl.js";
import { createVNode, unref, useSSRContext, withCtx } from "vue";
import { ssrRenderComponent } from "vue/server-renderer";
import { Head, router, useForm } from "@inertiajs/vue3";
import { useI18n } from "vue-i18n";
//#region resources/js/Pages/Admin/Plans/Create.vue
var _sfc_main = {
	__name: "Create",
	__ssrInlineRender: true,
	setup(__props) {
		const form = useForm({ name: "" });
		const { t } = useI18n();
		const submit = () => {
			form.post("/admin/plans");
		};
		const goBack = () => {
			router.get("/admin/plans");
		};
		return (_ctx, _push, _parent, _attrs) => {
			_push(`<!--[-->`);
			_push(ssrRenderComponent(unref(Head), { title: unref(t)("plans.createPlan") }, null, _parent));
			_push(ssrRenderComponent(_sfc_main$1, {
				"nav-items": unref(adminNavItems),
				"page-title": unref(t)("plans.createPlan")
			}, {
				default: withCtx((_, _push, _parent, _scopeId) => {
					if (_push) {
						_push(`<section class="space-y-6"${_scopeId}>`);
						_push(ssrRenderComponent(_sfc_main$2, null, null, _parent, _scopeId));
						_push(ssrRenderComponent(_sfc_main$3, {
							form: unref(form),
							"submit-label": unref(t)("plans.createPlan"),
							title: unref(t)("plans.newPlan"),
							description: unref(t)("plans.createDescription"),
							onSubmit: submit,
							onCancel: goBack
						}, null, _parent, _scopeId));
						_push(`</section>`);
					} else return [createVNode("section", { class: "space-y-6" }, [createVNode(_sfc_main$2), createVNode(_sfc_main$3, {
						form: unref(form),
						"submit-label": unref(t)("plans.createPlan"),
						title: unref(t)("plans.newPlan"),
						description: unref(t)("plans.createDescription"),
						onSubmit: submit,
						onCancel: goBack
					}, null, 8, [
						"form",
						"submit-label",
						"title",
						"description"
					])])];
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
	(ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Pages/Admin/Plans/Create.vue");
	return _sfc_setup ? _sfc_setup(props, ctx) : void 0;
};
//#endregion
export { _sfc_main as default };
