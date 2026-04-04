import { n as adminNavItems, t as _sfc_main$1 } from "./AdminLayout-XMnZ2yke.js";
import { t as _sfc_main$2 } from "./AdminBreadcrumbs-Ddp11j0e.js";
import { n as _sfc_main$3, t as useServerTable } from "./useServerTable-Cr3UKBMR.js";
import { computed, createVNode, onMounted, unref, useSSRContext, withCtx } from "vue";
import { ssrRenderComponent } from "vue/server-renderer";
import { Head } from "@inertiajs/vue3";
import { useI18n } from "vue-i18n";
//#region resources/js/Pages/Admin/ActivityLogs.vue
var _sfc_main = {
	__name: "ActivityLogs",
	__ssrInlineRender: true,
	setup(__props) {
		const { t } = useI18n();
		const { loading, rows: activityLogs, totalRecords, currentPage, rowsPerPage, search, sortBy, tableSortOrder, fetchRows: fetchActivityLogs, onPageChange: handlePageChange, onSortChange: handleSortChange } = useServerTable({
			endpoint: "/admin/activity-logs/records",
			defaultSortBy: "id",
			defaultSortDir: "desc"
		});
		const columns = computed(() => [
			{
				field: "id",
				header: t("common.id"),
				sortable: true
			},
			{
				field: "description",
				header: t("activityLogs.description"),
				sortable: true
			},
			{
				field: "event",
				header: t("activityLogs.event"),
				sortable: true
			},
			{
				field: "subject_display",
				header: t("activityLogs.subject")
			},
			{
				field: "causer_display",
				header: t("activityLogs.causer")
			},
			{
				field: "created_at_formatted",
				header: t("activityLogs.createdAt"),
				sortable: true,
				sortField: "created_at"
			}
		]);
		onMounted(() => {
			fetchActivityLogs();
		});
		return (_ctx, _push, _parent, _attrs) => {
			_push(`<!--[-->`);
			_push(ssrRenderComponent(unref(Head), { title: unref(t)("activityLogs.title") }, null, _parent));
			_push(ssrRenderComponent(_sfc_main$1, {
				"nav-items": unref(adminNavItems),
				"page-title": unref(t)("activityLogs.title")
			}, {
				default: withCtx((_, _push, _parent, _scopeId) => {
					if (_push) {
						_push(`<section class="space-y-6"${_scopeId}>`);
						_push(ssrRenderComponent(_sfc_main$2, null, null, _parent, _scopeId));
						_push(ssrRenderComponent(_sfc_main$3, {
							columns: columns.value,
							rows: unref(activityLogs),
							loading: unref(loading),
							"total-records": unref(totalRecords),
							"current-page": unref(currentPage),
							"rows-per-page": unref(rowsPerPage),
							search: unref(search),
							"sort-field": unref(sortBy),
							"sort-order": unref(tableSortOrder),
							"show-create": false,
							"show-actions": false,
							"search-label": unref(t)("activityLogs.searchLogs"),
							"table-title": unref(t)("activityLogs.tableTitle"),
							"onUpdate:search": ($event) => search.value = $event,
							onPageChange: unref(handlePageChange),
							onSortChange: unref(handleSortChange)
						}, null, _parent, _scopeId));
						_push(`</section>`);
					} else return [createVNode("section", { class: "space-y-6" }, [createVNode(_sfc_main$2), createVNode(_sfc_main$3, {
						columns: columns.value,
						rows: unref(activityLogs),
						loading: unref(loading),
						"total-records": unref(totalRecords),
						"current-page": unref(currentPage),
						"rows-per-page": unref(rowsPerPage),
						search: unref(search),
						"sort-field": unref(sortBy),
						"sort-order": unref(tableSortOrder),
						"show-create": false,
						"show-actions": false,
						"search-label": unref(t)("activityLogs.searchLogs"),
						"table-title": unref(t)("activityLogs.tableTitle"),
						"onUpdate:search": ($event) => search.value = $event,
						onPageChange: unref(handlePageChange),
						onSortChange: unref(handleSortChange)
					}, null, 8, [
						"columns",
						"rows",
						"loading",
						"total-records",
						"current-page",
						"rows-per-page",
						"search",
						"sort-field",
						"sort-order",
						"search-label",
						"table-title",
						"onUpdate:search",
						"onPageChange",
						"onSortChange"
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
	(ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Pages/Admin/ActivityLogs.vue");
	return _sfc_setup ? _sfc_setup(props, ctx) : void 0;
};
//#endregion
export { _sfc_main as default };
