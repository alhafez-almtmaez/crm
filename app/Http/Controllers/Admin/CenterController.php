<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CenterIndexRequest;
use App\Http\Requests\Admin\CenterStoreRequest;
use App\Http\Requests\Admin\CenterUpdateRequest;
use App\Models\Center;
use App\Services\Admin\ActivityLogService;
use App\Services\Admin\CenterService;
use App\Services\Admin\WhatsAppGroupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;
use Inertia\Response;

class CenterController extends Controller implements HasMiddleware
{
    public function __construct(
        private readonly CenterService $centerService,
        private readonly ActivityLogService $activityLogService,
        private readonly WhatsAppGroupService $whatsAppGroupService,
    )
    {
    }

    public static function middleware(): array
    {
        return [
            new Middleware('can:centers.view', only: ['index', 'records']),
            new Middleware('can:centers.view', only: ['activityLogs']),
            new Middleware('can:centers.create', only: ['create', 'store']),
            new Middleware('can:centers.update', only: ['edit', 'update']),
            new Middleware('can:centers.delete', only: ['destroy']),
        ];
    }

    public function index(): Response
    {
        return Inertia::render('Admin/Centers');
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Centers/Create', [
            'whatsappGroups' => $this->whatsAppGroupService->options(),
        ]);
    }

    public function edit(Center $center): Response
    {
        return Inertia::render('Admin/Centers/Edit', [
            'center' => [
                ...$center->only(['id', 'name', 'phone', 'group_serialized']),
                'working_days' => is_array($center->working_days) ? $center->working_days : [],
            ],
            'whatsappGroups' => $this->whatsAppGroupService->options(),
        ]);
    }

    public function records(CenterIndexRequest $request): JsonResponse
    {
        $centers = $this->centerService->list($request->validated());

        return response()->json([
            'data' => $centers->items(),
            'meta' => [
                'current_page' => $centers->currentPage(),
                'per_page' => $centers->perPage(),
                'total' => $centers->total(),
            ],
        ]);
    }

    public function store(CenterStoreRequest $request): RedirectResponse
    {
        $this->centerService->create($request->validated());

        return redirect()
            ->route('admin.centers.index')
            ->with('success', __('centers.created_successfully'));
    }

    public function update(CenterUpdateRequest $request, Center $center): RedirectResponse
    {
        $this->centerService->update($center, $request->validated());

        return redirect()
            ->route('admin.centers.index')
            ->with('success', __('centers.updated_successfully'));
    }

    public function destroy(Center $center): JsonResponse
    {
        $this->centerService->delete($center);

        return response()->json([
            'message' => __('centers.deleted_successfully'),
        ]);
    }

    public function activityLogs(Center $center): JsonResponse
    {
        return response()->json([
            'data' => $this->activityLogService->listForSubject(Center::class, $center->getKey()),
        ]);
    }
}
