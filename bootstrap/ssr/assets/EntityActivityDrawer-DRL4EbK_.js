import { r as useAppToast } from "./useSystemSettings-CGKqSGLN.js";
import { Fragment, computed, createBlock, createCommentVNode, createVNode, mergeProps, openBlock, ref, renderList, toDisplayString, unref, useSSRContext, watch, withCtx } from "vue";
import { ssrInterpolate, ssrRenderComponent, ssrRenderList } from "vue/server-renderer";
import { useI18n } from "vue-i18n";
import axios from "axios";
import Button from "primevue/button";
import Dialog from "primevue/dialog";
//#region resources/js/components/admin/EntityActivityDrawer.vue
var _sfc_main = {
	__name: "EntityActivityDrawer",
	__ssrInlineRender: true,
	props: {
		modelValue: {
			type: Boolean,
			default: false
		},
		endpoint: {
			type: String,
			default: ""
		},
		entityName: {
			type: String,
			default: ""
		}
	},
	emits: ["update:modelValue"],
	setup(__props, { emit: __emit }) {
		const props = __props;
		const emit = __emit;
		const { t } = useI18n();
		const appToast = useAppToast();
		const loading = ref(false);
		const logs = ref([]);
		const visible = computed({
			get: () => props.modelValue,
			set: (value) => emit("update:modelValue", value)
		});
		const asText = (value) => {
			if (value === null || value === void 0 || value === "") return "-";
			if (typeof value === "string" || typeof value === "number" || typeof value === "boolean") return String(value);
			return JSON.stringify(value);
		};
		const toDiffRows = (changes) => {
			const oldValues = changes?.old ?? {};
			const newValues = changes?.attributes ?? {};
			return [...new Set([...Object.keys(oldValues), ...Object.keys(newValues)])].map((key) => ({
				field: key,
				oldValue: asText(oldValues[key]),
				newValue: asText(newValues[key])
			}));
		};
		const fetchLogs = async () => {
			if (!props.endpoint) {
				logs.value = [];
				return;
			}
			loading.value = true;
			try {
				const { data } = await axios.get(props.endpoint);
				logs.value = (data?.data ?? []).map((log) => ({
					...log,
					diffRows: toDiffRows(log.changes)
				}));
			} catch (error) {
				appToast.fromAxiosError(error, {
					summary: t("notifications.loadFailedTitle"),
					fallback: t("notifications.loadFailedDetail")
				});
			} finally {
				loading.value = false;
			}
		};
		watch(() => props.modelValue, (isOpen) => {
			if (isOpen) fetchLogs();
		});
		return (_ctx, _push, _parent, _attrs) => {
			_push(ssrRenderComponent(unref(Dialog), mergeProps({
				visible: visible.value,
				"onUpdate:visible": ($event) => visible.value = $event,
				modal: "",
				"dismissable-mask": "",
				header: unref(t)("activityLogs.historyFor", { name: __props.entityName || "-" }),
				style: { width: "min(980px, 96vw)" }
			}, _attrs), {
				footer: withCtx((_, _push, _parent, _scopeId) => {
					if (_push) _push(ssrRenderComponent(unref(Button), {
						label: unref(t)("common.close"),
						severity: "secondary",
						text: "",
						onClick: ($event) => visible.value = false
					}, null, _parent, _scopeId));
					else return [createVNode(unref(Button), {
						label: unref(t)("common.close"),
						severity: "secondary",
						text: "",
						onClick: ($event) => visible.value = false
					}, null, 8, ["label", "onClick"])];
				}),
				default: withCtx((_, _push, _parent, _scopeId) => {
					if (_push) if (loading.value) _push(`<div class="py-12 text-center text-sm text-(--muted-foreground)"${_scopeId}>${ssrInterpolate(unref(t)("common.loading"))}</div>`);
					else if (logs.value.length === 0) _push(`<div class="py-12 text-center text-sm text-(--muted-foreground)"${_scopeId}>${ssrInterpolate(unref(t)("activityLogs.noHistory"))}</div>`);
					else {
						_push(`<div class="space-y-4"${_scopeId}><!--[-->`);
						ssrRenderList(logs.value, (log) => {
							_push(`<article class="rounded-md border border-(--border) bg-(--card) p-4"${_scopeId}><div class="flex flex-wrap items-center justify-between gap-3"${_scopeId}><div class="flex items-center gap-2"${_scopeId}><span class="text-sm font-semibold text-(--foreground)"${_scopeId}>${ssrInterpolate(log.event || log.description)}</span><span class="rounded-full bg-[color-mix(in_oklab,var(--accent)_14%,transparent)] px-2 py-1 text-xs font-medium text-(--foreground)"${_scopeId}>${ssrInterpolate(log.log_name || "default")}</span></div><p class="text-xs text-(--muted-foreground)"${_scopeId}>${ssrInterpolate(log.created_at_formatted)}</p></div><p class="mt-1 text-sm text-(--muted-foreground)"${_scopeId}>${ssrInterpolate(unref(t)("activityLogs.by"))}: ${ssrInterpolate(log.causer_display)}</p><div class="mt-3 overflow-x-auto"${_scopeId}><table class="w-full min-w-[540px] text-sm"${_scopeId}><thead${_scopeId}><tr class="border-b border-(--border)"${_scopeId}><th class="px-2 py-2 text-start font-semibold"${_scopeId}>${ssrInterpolate(unref(t)("activityLogs.field"))}</th><th class="px-2 py-2 text-start font-semibold"${_scopeId}>${ssrInterpolate(unref(t)("activityLogs.before"))}</th><th class="px-2 py-2 text-start font-semibold"${_scopeId}>${ssrInterpolate(unref(t)("activityLogs.after"))}</th></tr></thead><tbody${_scopeId}>`);
							if (log.diffRows.length === 0) _push(`<tr${_scopeId}><td class="px-2 py-3 text-(--muted-foreground)" colspan="3"${_scopeId}>${ssrInterpolate(unref(t)("activityLogs.noChanges"))}</td></tr>`);
							else _push(`<!---->`);
							_push(`<!--[-->`);
							ssrRenderList(log.diffRows, (row) => {
								_push(`<tr class="border-b border-(--border) last:border-0"${_scopeId}><td class="px-2 py-2 font-medium"${_scopeId}>${ssrInterpolate(row.field)}</td><td class="px-2 py-2 text-(--muted-foreground)"${_scopeId}>${ssrInterpolate(row.oldValue)}</td><td class="px-2 py-2 text-(--foreground)"${_scopeId}>${ssrInterpolate(row.newValue)}</td></tr>`);
							});
							_push(`<!--]--></tbody></table></div></article>`);
						});
						_push(`<!--]--></div>`);
					}
					else return [loading.value ? (openBlock(), createBlock("div", {
						key: 0,
						class: "py-12 text-center text-sm text-(--muted-foreground)"
					}, toDisplayString(unref(t)("common.loading")), 1)) : logs.value.length === 0 ? (openBlock(), createBlock("div", {
						key: 1,
						class: "py-12 text-center text-sm text-(--muted-foreground)"
					}, toDisplayString(unref(t)("activityLogs.noHistory")), 1)) : (openBlock(), createBlock("div", {
						key: 2,
						class: "space-y-4"
					}, [(openBlock(true), createBlock(Fragment, null, renderList(logs.value, (log) => {
						return openBlock(), createBlock("article", {
							key: log.id,
							class: "rounded-md border border-(--border) bg-(--card) p-4"
						}, [
							createVNode("div", { class: "flex flex-wrap items-center justify-between gap-3" }, [createVNode("div", { class: "flex items-center gap-2" }, [createVNode("span", { class: "text-sm font-semibold text-(--foreground)" }, toDisplayString(log.event || log.description), 1), createVNode("span", { class: "rounded-full bg-[color-mix(in_oklab,var(--accent)_14%,transparent)] px-2 py-1 text-xs font-medium text-(--foreground)" }, toDisplayString(log.log_name || "default"), 1)]), createVNode("p", { class: "text-xs text-(--muted-foreground)" }, toDisplayString(log.created_at_formatted), 1)]),
							createVNode("p", { class: "mt-1 text-sm text-(--muted-foreground)" }, toDisplayString(unref(t)("activityLogs.by")) + ": " + toDisplayString(log.causer_display), 1),
							createVNode("div", { class: "mt-3 overflow-x-auto" }, [createVNode("table", { class: "w-full min-w-[540px] text-sm" }, [createVNode("thead", null, [createVNode("tr", { class: "border-b border-(--border)" }, [
								createVNode("th", { class: "px-2 py-2 text-start font-semibold" }, toDisplayString(unref(t)("activityLogs.field")), 1),
								createVNode("th", { class: "px-2 py-2 text-start font-semibold" }, toDisplayString(unref(t)("activityLogs.before")), 1),
								createVNode("th", { class: "px-2 py-2 text-start font-semibold" }, toDisplayString(unref(t)("activityLogs.after")), 1)
							])]), createVNode("tbody", null, [log.diffRows.length === 0 ? (openBlock(), createBlock("tr", { key: 0 }, [createVNode("td", {
								class: "px-2 py-3 text-(--muted-foreground)",
								colspan: "3"
							}, toDisplayString(unref(t)("activityLogs.noChanges")), 1)])) : createCommentVNode("", true), (openBlock(true), createBlock(Fragment, null, renderList(log.diffRows, (row) => {
								return openBlock(), createBlock("tr", {
									key: `${log.id}-${row.field}`,
									class: "border-b border-(--border) last:border-0"
								}, [
									createVNode("td", { class: "px-2 py-2 font-medium" }, toDisplayString(row.field), 1),
									createVNode("td", { class: "px-2 py-2 text-(--muted-foreground)" }, toDisplayString(row.oldValue), 1),
									createVNode("td", { class: "px-2 py-2 text-(--foreground)" }, toDisplayString(row.newValue), 1)
								]);
							}), 128))])])])
						]);
					}), 128))]))];
				}),
				_: 1
			}, _parent));
		};
	}
};
var _sfc_setup = _sfc_main.setup;
_sfc_main.setup = (props, ctx) => {
	const ssrContext = useSSRContext();
	(ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/components/admin/EntityActivityDrawer.vue");
	return _sfc_setup ? _sfc_setup(props, ctx) : void 0;
};
//#endregion
export { _sfc_main as t };
