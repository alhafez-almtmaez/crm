<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\GroupIndexRequest;
use App\Http\Requests\Admin\GroupStoreRequest;
use App\Http\Requests\Admin\GroupUpdateRequest;
use App\Models\Center;
use App\Models\Group;
use App\Services\Admin\ActivityLogService;
use App\Services\Admin\AdminDataScopeService;
use App\Services\Admin\GroupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;
use Inertia\Response;

class GroupController extends Controller implements HasMiddleware
{
    public function __construct(
        private readonly GroupService $groupService,
        private readonly ActivityLogService $activityLogService,
        private readonly AdminDataScopeService $dataScope,
    ) {}

    public static function middleware(): array
    {
        return [
            new Middleware('can:groups.view', only: ['index', 'records', 'byCenter']),
            new Middleware('can:groups.view', only: ['activityLogs']),
            new Middleware('can:groups.create', only: ['create', 'store']),
            new Middleware('can:groups.update', only: ['edit', 'update']),
            new Middleware('can:groups.delete', only: ['destroy']),
        ];
    }

    public function index(): Response
    {
        return Inertia::render('Admin/Groups');
    }

    public function homeworkReport(string $publicId): Response
    {
        $report = $this->groupService->homeworkReportPayload($publicId);
        $title = 'واجبات المرة القادمة | مشروع الحافظ المتميز';
        $description = trim(($report['center_name'] ?? '').' / '.($report['group_name'] ?? ''));
        $imageUrl = asset('media/logos/logo.png');

        return Inertia::render('Groups/HomeworkReport', [
            'report' => $report,
        ])->withViewData([
            'pageMeta' => [
                'title' => $title,
                'description' => $description,
                'url' => url()->current(),
                'image' => $imageUrl,
                'type' => 'website',
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Groups/Create', [
            'centers' => $this->groupService->centerOptions(),
        ]);
    }

    public function edit(Group $group): Response
    {
        $this->dataScope->abortUnlessCanAccessGroup($group);

        return Inertia::render('Admin/Groups/Edit', [
            'group' => $group->only(['id', 'name', 'center_id']),
            'centers' => $this->groupService->centerOptions(),
        ]);
    }

    public function records(GroupIndexRequest $request): JsonResponse
    {
        $groups = $this->groupService->list($request->validated());

        return response()->json([
            'data' => $groups->items(),
            'meta' => [
                'current_page' => $groups->currentPage(),
                'per_page' => $groups->perPage(),
                'total' => $groups->total(),
            ],
        ]);
    }

    public function store(GroupStoreRequest $request): RedirectResponse
    {
        $this->groupService->create($request->validated());

        return redirect()
            ->route('admin.groups.index')
            ->with('success', __('groups.created_successfully'));
    }

    public function update(GroupUpdateRequest $request, Group $group): RedirectResponse
    {
        $this->dataScope->abortUnlessCanAccessGroup($group);

        $this->groupService->update($group, $request->validated());

        return redirect()
            ->route('admin.groups.index')
            ->with('success', __('groups.updated_successfully'));
    }

    public function destroy(Group $group): JsonResponse
    {
        $this->dataScope->abortUnlessCanAccessGroup($group);

        $this->groupService->delete($group);

        return response()->json([
            'message' => __('groups.deleted_successfully'),
        ]);
    }

    public function activityLogs(Group $group): JsonResponse
    {
        $this->dataScope->abortUnlessCanAccessGroup($group);

        return response()->json([
            'data' => $this->activityLogService->listForSubject(Group::class, $group->getKey()),
        ]);
    }

    public function byCenter(Center $center): JsonResponse
    {
        $this->dataScope->abortUnlessCanAccessCenter($center);

        return response()->json([
            'data' => $this->groupService->listByCenter($center),
        ]);
    }
}
