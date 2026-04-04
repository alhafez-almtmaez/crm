<?php

namespace App\Services\Auth;

use App\Models\Role;
use Illuminate\Support\Arr;
use InvalidArgumentException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class PermissionSyncService
{
    /**
     * @return array{guard: string, permission_count: int, role_count: int, pruned_permissions: int}
     */
    public function sync(bool $pruneMissing = false): array
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $guard = (string) config('permissions.guard', 'web');
        $definedPermissions = $this->definedPermissions();

        foreach ($definedPermissions as $permissionName) {
            Permission::query()->firstOrCreate([
                'name' => $permissionName,
                'guard_name' => $guard,
            ]);
        }

        $prunedCount = 0;
        if ($pruneMissing) {
            $prunedCount = Permission::query()
                ->where('guard_name', $guard)
                ->whereNotIn('name', $definedPermissions)
                ->delete();
        }

        $roleMap = config('permissions.roles', []);
        if (! is_array($roleMap)) {
            throw new InvalidArgumentException('permissions.roles must be an array.');
        }

        foreach ($roleMap as $roleName => $rolePermissions) {
            $role = Role::query()->firstOrCreate([
                'name' => (string) $roleName,
                'guard_name' => $guard,
            ]);

            $permissionsToAssign = $this->resolveRolePermissions($rolePermissions, $definedPermissions);
            $role->syncPermissions($permissionsToAssign);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return [
            'guard' => $guard,
            'permission_count' => count($definedPermissions),
            'role_count' => count($roleMap),
            'pruned_permissions' => $prunedCount,
        ];
    }

    /**
     * @return list<string>
     */
    private function definedPermissions(): array
    {
        $configured = config('permissions.permissions', []);
        if (! is_array($configured)) {
            throw new InvalidArgumentException('permissions.permissions must be an array.');
        }

        $crudActions = config('permissions.crud_actions', []);
        if (! is_array($crudActions)) {
            throw new InvalidArgumentException('permissions.crud_actions must be an array.');
        }

        $crudResources = config('permissions.crud_resources', []);
        if (! is_array($crudResources)) {
            throw new InvalidArgumentException('permissions.crud_resources must be an array.');
        }

        $generatedCrudPermissions = [];

        foreach ($crudResources as $resource) {
            if (! is_string($resource) || trim($resource) === '') {
                continue;
            }

            $resourceName = trim($resource);

            foreach ($crudActions as $action) {
                if (! is_string($action) || trim($action) === '') {
                    continue;
                }

                $generatedCrudPermissions[] = "{$resourceName}.".trim($action);
            }
        }

        $flat = Arr::flatten([...$configured, ...$generatedCrudPermissions]);
        $permissions = [];

        foreach ($flat as $item) {
            if (! is_string($item)) {
                continue;
            }

            $name = trim($item);
            if ($name === '') {
                continue;
            }

            $permissions[$name] = true;
        }

        return array_keys($permissions);
    }

    /**
     * @param mixed $rolePermissions
     * @param list<string> $definedPermissions
     * @return list<string>
     */
    private function resolveRolePermissions(mixed $rolePermissions, array $definedPermissions): array
    {
        if (! is_array($rolePermissions)) {
            return [];
        }

        $requested = [];
        foreach (Arr::flatten($rolePermissions) as $item) {
            if (! is_string($item)) {
                continue;
            }

            $requested[] = trim($item);
        }

        if (in_array('*', $requested, true)) {
            return $definedPermissions;
        }

        $allowed = array_flip($definedPermissions);

        return array_values(array_filter($requested, static fn (string $name): bool => isset($allowed[$name])));
    }
}
