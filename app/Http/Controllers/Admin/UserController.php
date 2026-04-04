<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UserIndexRequest;
use App\Http\Requests\Admin\UserStoreRequest;
use App\Http\Requests\Admin\UserUpdateRequest;
use App\Models\User;
use App\Services\Admin\ActivityLogService;
use App\Services\Admin\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller implements HasMiddleware
{
    public function __construct(
        private readonly UserService $userService,
        private readonly ActivityLogService $activityLogService,
    )
    {
    }

    public static function middleware(): array
    {
        return [
            new Middleware('can:users.view', only: ['index', 'records']),
            new Middleware('can:users.view', only: ['activityLogs']),
            new Middleware('can:users.create', only: ['create', 'store']),
            new Middleware('can:users.update', only: ['edit', 'update']),
            new Middleware('can:users.delete', only: ['destroy']),
        ];
    }

    public function index(): Response
    {
        return Inertia::render('Admin/Users');
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Users/Create', [
            'roles' => $this->userService->roleOptions(),
        ]);
    }

    public function edit(User $user): Response
    {
        $user->loadMissing('roles:id');

        return Inertia::render('Admin/Users/Edit', [
            'user' => [
                ...$user->only(['id', 'name', 'email']),
                'role_id' => $user->roles->first()?->id,
            ],
            'roles' => $this->userService->roleOptions(),
        ]);
    }

    public function records(UserIndexRequest $request): JsonResponse
    {
        $users = $this->userService->list($request->validated());

        return response()->json([
            'data' => $users->items(),
            'meta' => [
                'current_page' => $users->currentPage(),
                'per_page' => $users->perPage(),
                'total' => $users->total(),
            ],
        ]);
    }

    public function store(UserStoreRequest $request): RedirectResponse
    {
        $this->userService->create($request->validated());

        return redirect()
            ->route('admin.users.index')
            ->with('success', 'User created successfully.');
    }

    public function update(UserUpdateRequest $request, User $user): RedirectResponse
    {
        $this->userService->update($user, $request->validated());

        return redirect()
            ->route('admin.users.index')
            ->with('success', 'User updated successfully.');
    }

    public function destroy(User $user): JsonResponse
    {
        $this->userService->delete($user);

        return response()->json([
            'message' => 'User deleted successfully.',
        ]);
    }

    public function activityLogs(User $user): JsonResponse
    {
        return response()->json([
            'data' => $this->activityLogService->listForSubject(User::class, $user->getKey()),
        ]);
    }
}
