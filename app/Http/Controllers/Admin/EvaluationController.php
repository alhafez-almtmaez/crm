<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\EvaluationIndexRequest;
use App\Http\Requests\Admin\EvaluationStoreRequest;
use App\Http\Requests\Admin\EvaluationUpdateRequest;
use App\Models\Evaluation;
use App\Services\Admin\EvaluationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;
use Inertia\Response;

class EvaluationController extends Controller implements HasMiddleware
{
    public function __construct(private readonly EvaluationService $service)
    {
    }

    public static function middleware(): array
    {
        return [
            new Middleware('can:evaluations.view', only: ['index', 'records']),
            new Middleware('can:evaluations.create', only: ['create', 'store']),
            new Middleware('can:evaluations.update', only: ['edit', 'update', 'sendAbsenceAlerts']),
            new Middleware('can:evaluations.delete', only: ['destroy']),
        ];
    }

    public function index(): Response
    {
        return Inertia::render('Admin/Evaluations');
    }

    public function records(EvaluationIndexRequest $request): JsonResponse
    {
        $rows = $this->service->list($request->validated());

        return response()->json([
            'data' => $rows->items(),
            'meta' => [
                'current_page' => $rows->currentPage(),
                'per_page' => $rows->perPage(),
                'total' => $rows->total(),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        $query = $request->validate([
            'center_id' => ['nullable', 'integer', 'exists:centers,id'],
            'date' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $centerId = isset($query['center_id']) ? (int) $query['center_id'] : null;
        $date = isset($query['date']) ? (string) $query['date'] : null;
        $formPayload = $this->service->createFormPayload($centerId, $date);

        return Inertia::render('Admin/Evaluations/Create', [
            'centers' => $this->service->centerOptions(),
            ...$formPayload,
        ]);
    }

    public function edit(Evaluation $evaluation): Response
    {
        return Inertia::render('Admin/Evaluations/Edit', [
            'evaluation' => [
                'id' => $evaluation->id,
                'center_id' => $evaluation->center_id,
                'date' => $evaluation->date?->format('Y-m-d'),
                'center_name' => $evaluation->center?->name,
            ],
            'centers' => $this->service->centerOptions(),
            'students' => $this->service->editStudentRows($evaluation),
        ]);
    }

    public function store(EvaluationStoreRequest $request): RedirectResponse
    {
        $evaluation = $this->service->create($request->validated());

        return redirect()
            ->route('admin.evaluations.edit', $evaluation)
            ->with('success', __('evaluations.created_successfully'));
    }

    public function update(EvaluationUpdateRequest $request, Evaluation $evaluation): RedirectResponse
    {
        $this->service->update($evaluation, $request->validated());

        return redirect()
            ->route('admin.evaluations.index')
            ->with('success', __('evaluations.updated_successfully'));
    }

    public function destroy(Evaluation $evaluation): JsonResponse
    {
        $this->service->delete($evaluation);

        return response()->json([
            'message' => __('evaluations.deleted_successfully'),
        ]);
    }

    public function sendAbsenceAlerts(Evaluation $evaluation): JsonResponse
    {
        $result = $this->service->sendAbsenceAlerts($evaluation);
        $hasErrors = $result['errors'] !== [];

        if ($hasErrors) {
            return response()->json([
                'message' => __('evaluations.absence_alerts_completed_with_errors'),
                'meta' => $result,
            ], 422);
        }

        return response()->json([
            'message' => __('evaluations.absence_alerts_sent_successfully'),
            'meta' => $result,
        ]);
    }
}
