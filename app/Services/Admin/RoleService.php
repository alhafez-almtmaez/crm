<?php

namespace App\Services\Admin;

use App\Models\Role;
use App\Services\System\DateTimeFormatterService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class RoleService
{
    public function __construct(private readonly DateTimeFormatterService $dateTimeFormatter)
    {
    }

    /**
     * @param array<string, mixed> $filters
     */
    public function list(array $filters): LengthAwarePaginator
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $perPage = (int) ($filters['per_page'] ?? 10);
        $sortBy = (string) ($filters['sort_by'] ?? 'id');
        $sortDir = (string) ($filters['sort_dir'] ?? 'desc');
        $allowedSorts = ['id', 'name', 'permissions_count', 'created_at'];

        $sortBy = in_array($sortBy, $allowedSorts, true) ? $sortBy : 'id';
        $sortDir = in_array($sortDir, ['asc', 'desc'], true) ? $sortDir : 'desc';

        $query = Role::query()
            ->select(['id', 'name', 'guard_name', 'created_at'])
            ->withCount('permissions')
            ->where('guard_name', 'web')
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($builder) use ($search): void {
                    $builder->where('name', 'like', "%{$search}%");
                });
            })
            ->orderBy($sortBy, $sortDir);

        $roles = $query->paginate($perPage)->withQueryString();
        $roles->setCollection(
            $roles->getCollection()->map(function (Role $role): Role {
                $role->setAttribute('created_at_formatted', $this->dateTimeFormatter->formatForAdmin($role->created_at));

                return $role;
            }),
        );

        return $roles;
    }

    /**
     * @return array<int, array{id: int, name: string}>
     */
    public function permissionsOptions(): array
    {
        /** @var array<int, array{id: int, name: string}> $permissions */
        $permissions = Permission::query()
            ->where('guard_name', 'web')
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Permission $permission): array => [
                'id' => $permission->id,
                'name' => $permission->name,
            ])
            ->all();

        return $permissions;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function create(array $data): Role
    {
        $role = Role::query()->create([
            'name' => $data['name'],
            'guard_name' => 'web',
        ]);

        $permissionIds = array_map('intval', $data['permissions'] ?? []);
        $permissionNames = Permission::query()
            ->whereIn('id', $permissionIds)
            ->pluck('name')
            ->all();

        $role->syncPermissions($permissionNames);

        return $role->refresh();
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(Role $role, array $data): Role
    {
        $this->ensureEditableWebRole($role);

        $role->update([
            'name' => $data['name'],
        ]);

        $permissionIds = array_map('intval', $data['permissions'] ?? []);
        $permissionNames = Permission::query()
            ->whereIn('id', $permissionIds)
            ->pluck('name')
            ->all();

        $role->syncPermissions($permissionNames);

        return $role->refresh();
    }

    public function delete(Role $role): void
    {
        $this->ensureWebRole($role);

        if ($role->name === 'admin') {
            throw ValidationException::withMessages([
                'role' => __('roles.admin_cannot_be_deleted'),
            ]);
        }

        $assignedUsersCount = DB::table('model_has_roles')
            ->where('role_id', $role->id)
            ->count();

        if ($assignedUsersCount > 0) {
            throw ValidationException::withMessages([
                'role' => __('roles.assigned_cannot_be_deleted'),
            ]);
        }

        $role->delete();
    }

    /**
     * @return array{id: int, name: string, permission_ids: array<int, int>}
     */
    public function editableRolePayload(Role $role): array
    {
        $this->ensureEditableWebRole($role);
        $role->loadMissing('permissions:id,name');

        return [
            'id' => $role->id,
            'name' => $role->name,
            'permission_ids' => $role->permissions->pluck('id')->all(),
        ];
    }

    private function ensureEditableWebRole(Role $role): void
    {
        $this->ensureWebRole($role);

        if ($role->name === 'admin') {
            throw new AccessDeniedHttpException(__('roles.admin_cannot_be_edited'));
        }
    }

    private function ensureWebRole(Role $role): void
    {
        if ($role->guard_name !== 'web') {
            throw new NotFoundHttpException();
        }
    }
}
