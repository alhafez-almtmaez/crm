<?php

namespace App\Services\Admin;

use App\Models\User;
use App\Services\System\DateTimeFormatterService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use App\Models\Role;

class UserService
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
        $allowedSorts = ['id', 'name', 'email', 'created_at'];

        $sortBy = in_array($sortBy, $allowedSorts, true) ? $sortBy : 'id';
        $sortDir = in_array($sortDir, ['asc', 'desc'], true) ? $sortDir : 'desc';

        $query = User::query()
            ->select(['id', 'name', 'email', 'created_at'])
            ->with('roles:id,name')
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($builder) use ($search): void {
                    $builder
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->orderBy($sortBy, $sortDir);

        $users = $query->paginate($perPage)->withQueryString();
        $users->setCollection(
            $users->getCollection()->map(function (User $user): User {
                $user->setAttribute('role_name', $user->roles->first()?->name ?? '-');
                $user->setAttribute('created_at_formatted', $this->dateTimeFormatter->formatForAdmin($user->created_at));

                return $user;
            }),
        );

        return $users;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function create(array $data): User
    {
        $user = User::query()->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
        ]);

        $role = Role::query()
            ->where('guard_name', 'web')
            ->findOrFail((int) $data['role_id']);

        $user->syncRoles([$role->name]);

        return $user->refresh();
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(User $user, array $data): User
    {
        $payload = [
            'name' => $data['name'],
            'email' => $data['email'],
        ];

        if (! empty($data['password'])) {
            $payload['password'] = $data['password'];
        }

        $user->update($payload);
        $role = Role::query()
            ->where('guard_name', 'web')
            ->findOrFail((int) $data['role_id']);
        $user->syncRoles([$role->name]);

        return $user->refresh();
    }

    /**
     * @return array<int, array{id: int, name: string}>
     */
    public function roleOptions(): array
    {
        /** @var array<int, array{id: int, name: string}> $roles */
        $roles = Role::query()
            ->where('guard_name', 'web')
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Role $role): array => [
                'id' => $role->id,
                'name' => $role->name,
            ])
            ->all();

        return $roles;
    }

    public function delete(User $user): void
    {
        if (Auth::id() === $user->id) {
            throw ValidationException::withMessages([
                'user' => 'You cannot delete your own account.',
            ]);
        }

        $user->delete();
    }
}
