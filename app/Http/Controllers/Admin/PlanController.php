<?php

namespace App\Http\Controllers\Admin;

use App\Exports\PlanPointsExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PlanIndexRequest;
use App\Http\Requests\Admin\PlanPointImportRequest;
use App\Http\Requests\Admin\PlanStoreRequest;
use App\Http\Requests\Admin\PlanUpdateRequest;
use App\Models\Plan;
use App\Services\Admin\ActivityLogService;
use App\Services\Admin\PlanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PlanController extends Controller implements HasMiddleware
{
    public function __construct(
        private readonly PlanService $planService,
        private readonly ActivityLogService $activityLogService,
    ) {}

    public static function middleware(): array
    {
        return [
            new Middleware('can:plans.view', only: ['index', 'records']),
            new Middleware('can:plans.view', only: ['activityLogs']),
            new Middleware('can:plans.view', only: ['exportPoints']),
            new Middleware('can:plans.create', only: ['create', 'store']),
            new Middleware('can:plans.update', only: ['edit', 'update']),
            new Middleware('can:plans.update', only: ['importPoints']),
            new Middleware('can:plans.delete', only: ['destroy']),
        ];
    }

    public function index(): Response
    {
        return Inertia::render('Admin/Plans');
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Plans/Create');
    }

    public function edit(Plan $plan): Response
    {
        return Inertia::render('Admin/Plans/Edit', [
            'plan' => $plan->only(['id', 'name']),
        ]);
    }

    public function records(PlanIndexRequest $request): JsonResponse
    {
        $plans = $this->planService->list($request->validated());

        return response()->json([
            'data' => $plans->items(),
            'meta' => [
                'current_page' => $plans->currentPage(),
                'per_page' => $plans->perPage(),
                'total' => $plans->total(),
            ],
        ]);
    }

    public function store(PlanStoreRequest $request): RedirectResponse
    {
        $this->planService->create($request->validated());

        return redirect()
            ->route('admin.plans.index')
            ->with('success', __('plans.created_successfully'));
    }

    public function update(PlanUpdateRequest $request, Plan $plan): RedirectResponse
    {
        $this->planService->update($plan, $request->validated());

        return redirect()
            ->route('admin.plans.index')
            ->with('success', __('plans.updated_successfully'));
    }

    public function destroy(Plan $plan): JsonResponse
    {
        $this->planService->delete($plan);

        return response()->json([
            'message' => __('plans.deleted_successfully'),
        ]);
    }

    public function activityLogs(Plan $plan): JsonResponse
    {
        return response()->json([
            'data' => $this->activityLogService->listForSubject(Plan::class, $plan->getKey()),
        ]);
    }

    public function exportPoints(Plan $plan): BinaryFileResponse
    {
        $safeName = str($plan->name)->replaceMatches('/[^\pL\pN\-_.]+/u', '-')->trim('-')->limit(80, '');
        $fileName = 'plan-points-'.$plan->id.'-'.$safeName.'-'.now()->format('Y-m-d-His').'.xlsx';

        $previousReporting = error_reporting();

        try {
            error_reporting($previousReporting & ~E_DEPRECATED);

            return Excel::download(new PlanPointsExport($this->planService->pointRows($plan)), $fileName);
        } finally {
            error_reporting($previousReporting);
        }
    }

    public function importPoints(PlanPointImportRequest $request, Plan $plan): JsonResponse
    {
        $result = $this->planService->importPointFile($plan, $request->file('file'));

        if ($result['imported'] === 0 && $result['errors'] !== []) {
            return response()->json([
                'message' => __('plans.points_import_failed'),
                'errors' => [
                    'file' => $result['errors'],
                ],
            ], 422);
        }

        return response()->json([
            'message' => __('plans.points_import_completed', [
                'imported' => $result['imported'],
                'skipped' => $result['skipped'],
            ]),
            'meta' => $result,
        ]);
    }
}
