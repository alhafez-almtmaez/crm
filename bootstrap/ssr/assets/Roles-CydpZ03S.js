import { n as adminNavItems, t as _sfc_main$1 } from "./AdminLayout-XMnZ2yke.js";
import { t as _sfc_main$2 } from "./AdminBreadcrumbs-Ddp11j0e.js";
import { r as useAppToast } from "./useSystemSettings-CGKqSGLN.js";
import { n as _sfc_main$3, t as useServerTable } from "./useServerTable-Cr3UKBMR.js";
import { t as _sfc_main$4 } from "./EntityActivityDrawer-DRL4EbK_.js";
import { computed, createVNode, onMounted, ref, unref, useSSRContext, withCtx } from "vue";
import { ssrRenderComponent } from "vue/server-renderer";
import { Head, router } from "@inertiajs/vue3";
import { useI18n } from "vue-i18n";
import axios from "axios";
import ConfirmPopup from "primevue/confirmpopup";
import { useConfirm } from "primevue/useconfirm";
//#region resources/js/Pages/Admin/Roles.vue
var _sfc_main = {
	__name: "Roles",
	__ssrInlineRender: true,
	setup(__props) {
		const confirm = useConfirm();
		const appToast = useAppToast();
		const { t } = useI18n();
		const { loading, rows: roles, totalRecords, currentPage, rowsPerPage, search, sortBy, tableSortOrder, fetchRows: fetchRoles, onPageChange: handlePageChange, onSortChange: handleSortChange } = useServerTable({
			endpoint: "/admin/roles/records",
			defaultSortBy: "id",
			defaultSortDir: "desc"
		});
		const historyVisible = ref(false);
		const historyEndpoint = ref("");
		const historyEntityName = ref("");
		const columns = computed(() => [
			{
				field: "id",
				header: t("common.id"),
				sortable: true
			},
			{
				field: "name",
				header: t("roles.roleName"),
				sortable: true
			},
			{
				field: "permissions_count",
				header: t("roles.permissionsCount"),
				sortable: true
			},
			{
				field: "created_at_formatted",
				header: t("roles.createdAt"),
				sortable: true,
				sortField: "created_at"
			}
		]);
		const openCreate = () => {
			router.get("/admin/roles/create");
		};
		const openEdit = (role) => {
			if (role.name === "admin") {
				appToast.info(t("roles.adminRoleProtected"));
				return;
			}
			router.get("/admin/roles/" + role.id + "/edit");
		};
		const deleteRole = async (role) => {
			try {
				const { data } = await axios.delete("/admin/roles/" + role.id);
				appToast.success(data?.message ?? t("notifications.roleDeleted"));
				await fetchRoles();
			} catch (error) {
				appToast.fromAxiosError(error, {
					summary: t("notifications.deleteFailedTitle"),
					fallback: t("notifications.deleteRoleFailed")
				});
			}
		};
		const askDeleteRole = ({ data: role, event }) => {
			if (role.name === "admin") {
				appToast.info(t("roles.adminRoleProtected"));
				return;
			}
			const target = event?.currentTarget ?? event?.target ?? document.body;
			confirm.require({
				target,
				message: t("roles.deleteConfirm", { name: role.name }),
				icon: "pi pi-exclamation-triangle",
				rejectProps: {
					label: t("common.cancel"),
					severity: "secondary",
					text: true
				},
				acceptProps: {
					label: t("roles.deleteRole"),
					severity: "danger"
				},
				accept: () => {
					deleteRole(role);
				}
			});
		};
		const openHistory = (role) => {
			historyEntityName.value = role.name;
			historyEndpoint.value = `/admin/roles/${role.id}/activity-logs`;
			historyVisible.value = true;
		};
		onMounted(() => {
			fetchRoles();
		});
		return (_ctx, _push, _parent, _attrs) => {
			_push(`<!--[-->`);
			_push(ssrRenderComponent(unref(Head), { title: unref(t)("roles.title") }, null, _parent));
			_push(ssrRenderComponent(_sfc_main$1, {
				"nav-items": unref(adminNavItems),
				"page-title": unref(t)("roles.title")
			}, {
				default: withCtx((_, _push, _parent, _scopeId) => {
					if (_push) {
						_push(`<section class="space-y-6"${_scopeId}>`);
						_push(ssrRenderComponent(_sfc_main$2, null, null, _parent, _scopeId));
						_push(ssrRenderComponent(_sfc_main$3, {
							columns: columns.value,
							rows: unref(roles),
							loading: unref(loading),
							"total-records": unref(totalRecords),
							"current-page": unref(currentPage),
							"rows-per-page": unref(rowsPerPage),
							search: unref(search),
							"sort-field": unref(sortBy),
							"sort-order": unref(tableSortOrder),
							"create-label": unref(t)("roles.createRole"),
							"search-label": unref(t)("roles.searchRoles"),
							"table-title": unref(t)("roles.tableTitle"),
							"show-history": true,
							"onUpdate:search": ($event) => search.value = $event,
							onPageChange: unref(handlePageChange),
							onSortChange: unref(handleSortChange),
							onCreate: openCreate,
							onHistory: openHistory,
							onEdit: openEdit,
							onDelete: askDeleteRole
						}, null, _parent, _scopeId));
						_push(ssrRenderComponent(unref(ConfirmPopup), null, null, _parent, _scopeId));
						_push(ssrRenderComponent(_sfc_main$4, {
							modelValue: historyVisible.value,
							"onUpdate:modelValue": ($event) => historyVisible.value = $event,
							endpoint: historyEndpoint.value,
							"entity-name": historyEntityName.value
						}, null, _parent, _scopeId));
						_push(`</section>`);
					} else return [createVNode("section", { class: "space-y-6" }, [
						createVNode(_sfc_main$2),
						createVNode(_sfc_main$3, {
							columns: columns.value,
							rows: unref(roles),
							loading: unref(loading),
							"total-records": unref(totalRecords),
							"current-page": unref(currentPage),
							"rows-per-page": unref(rowsPerPage),
							search: unref(search),
							"sort-field": unref(sortBy),
							"sort-order": unref(tableSortOrder),
							"create-label": unref(t)("roles.createRole"),
							"search-label": unref(t)("roles.searchRoles"),
							"table-title": unref(t)("roles.tableTitle"),
							"show-history": true,
							"onUpdate:search": ($event) => search.value = $event,
							onPageChange: unref(handlePageChange),
							onSortChange: unref(handleSortChange),
							onCreate: openCreate,
							onHistory: openHistory,
							onEdit: openEdit,
							onDelete: askDeleteRole
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
							"create-label",
							"search-label",
							"table-title",
							"onUpdate:search",
							"onPageChange",
							"onSortChange"
						]),
						createVNode(unref(ConfirmPopup)),
						createVNode(_sfc_main$4, {
							modelValue: historyVisible.value,
							"onUpdate:modelValue": ($event) => historyVisible.value = $event,
							endpoint: historyEndpoint.value,
							"entity-name": historyEntityName.value
						}, null, 8, [
							"modelValue",
							"onUpdate:modelValue",
							"endpoint",
							"entity-name"
						])
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
	(ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Pages/Admin/Roles.vue");
	return _sfc_setup ? _sfc_setup(props, ctx) : void 0;
};
//#endregion
export { _sfc_main as default };
