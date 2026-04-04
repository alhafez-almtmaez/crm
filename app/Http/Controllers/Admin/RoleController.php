<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RoleIndexRequest;
use App\Http\Requests\Admin\RoleStoreRequest;
use App\Http\Requests\Admin\RoleUpdateRequest;
use App\Models\Role;
use App\Services\Admin\ActivityLogService;
use App\Services\Admin\RoleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;
use Inertia\Response;

class RoleController extends Controller implements HasMiddleware
{
    public function __construct(
        private readonly RoleService $roleService,
        private readonly ActivityLogService $activityLogService,
    )
    {
    }

    public static function middleware(): array
    {
        return [
            new Middleware('can:roles.view', only: ['index', 'records']),
            new Middleware('can:roles.view', only: ['activityLogs']),
            new Middleware('can:roles.create', only: ['create', 'store']),
            new Middleware('can:roles.update', only: ['edit', 'update']),
            new Middleware('can:roles.delete', only: ['destroy']),
        ];
    }

    public function index(): Response
    {
        return Inertia::render('Admin/Roles');
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Roles/Create', [
            'permissions' => $this->roleService->permissionsOptions(),
        ]);
    }

    public function edit(Role $role): Response
    {
        return Inertia::render('Admin/Roles/Edit', [
            'role' => $this->roleService->editableRolePayload($role),
            'permissions' => $this->roleService->permissionsOptions(),
        ]);
    }

    public function records(RoleIndexRequest $request): JsonResponse
    {
        $roles = $this->roleService->list($request->validated());

        return response()->json([
            'data' => $roles->items(),
            'meta' => [
                'current_page' => $roles->currentPage(),
                'per_page' => $roles->perPage(),
                'total' => $roles->total(),
            ],
        ]);
    }

    public function store(RoleStoreRequest $request): RedirectResponse
    {
        $this->roleService->create($request->validated());

        return redirect()
            ->route('admin.roles.index')
            ->with('success', __('roles.created_successfully'));
    }

    public function update(RoleUpdateRequest $request, Role $role): RedirectResponse
    {
        $this->roleService->update($role, $request->validated());

        return redirect()
            ->route('admin.roles.index')
            ->with('success', __('roles.updated_successfully'));
    }

    public function destroy(Role $role): JsonResponse
    {
        $this->roleService->delete($role);

        return response()->json([
            'message' => __('roles.deleted_successfully'),
        ]);
    }

    public function activityLogs(Role $role): JsonResponse
    {
        return response()->json([
            'data' => $this->activityLogService->listForSubject(Role::class, $role->getKey()),
        ]);
    }

}
