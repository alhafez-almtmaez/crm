<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\EvaluationIndexRequest;
use App\Http\Requests\Admin\EvaluationStoreRequest;
use App\Http\Requests\Admin\EvaluationUpdateRequest;
use App\Models\AbsenceRuleExecutionLog;
use App\Models\Evaluation;
use App\Services\Admin\AdminDataScopeService;
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
    public function __construct(
        private readonly EvaluationService $service,
        private readonly AdminDataScopeService $dataScope,
    ) {}

    public static function middleware(): array
    {
        return [
            new Middleware('can:evaluations.view', only: ['index', 'records', 'absenceAlertPreviews', 'messageLogs']),
            new Middleware('can:evaluations.create', only: ['create', 'store']),
            new Middleware('can:evaluations.update', only: ['edit', 'update', 'sendAbsenceAlerts', 'resendMessageLog']),
            new Middleware('can:evaluations.delete', only: ['destroy']),
        ];
    }

    public function index(): Response
    {
        return Inertia::render('Admin/Evaluations', [
            'centers' => $this->service->centerOptions(),
        ]);
    }

    public function report(string $publicId): Response
    {
        $report = $this->service->reportPayload($publicId);
        $title = 'تقييمات الطلاب | مشروع الحافظ المتميز';
        $description = collect([
            $report['center_name'] ?? null,
            $report['group_name'] ?? null,
            $report['date'] ?? null,
        ])->filter()->implode(' / ');
        $imageUrl = asset('media/logos/logo.png');

        return Inertia::render('Evaluations/Report', [
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

    public function create(Request $request): RedirectResponse
    {
        return redirect()->route('admin.daily-follow-up.index', [
            ...$request->only(['center_id', 'group_id', 'date', 'evaluation_type']),
            'section' => 'evaluation',
        ]);
    }

    public function edit(Evaluation $evaluation): Response
    {
        $this->dataScope->abortUnlessCanAccessEvaluation($evaluation);
        $evaluation->loadMissing(['group.center', 'center']);

        $centerId = $evaluation->group?->center_id ?? $evaluation->center_id;
        $centerName = $evaluation->group?->center?->name ?? $evaluation->center?->name;

        return Inertia::render('Admin/Evaluations/Edit', [
            'evaluation' => [
                'id' => $evaluation->id,
                'center_id' => $centerId,
                'group_id' => $evaluation->group_id,
                'date' => $evaluation->date?->format('Y-m-d'),
                'center_name' => $centerName,
                'group_name' => $evaluation->group?->name,
                'evaluation_type' => (int) ($evaluation->evaluation_type ?? Evaluation::TYPE_ALHIFZ),
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
        $this->dataScope->abortUnlessCanAccessEvaluation($evaluation);

        $this->service->update($evaluation, $request->validated());

        return redirect()
            ->route('admin.evaluations.index')
            ->with('success', __('evaluations.updated_successfully'));
    }

    public function destroy(Evaluation $evaluation): JsonResponse
    {
        $this->dataScope->abortUnlessCanAccessEvaluation($evaluation);

        $this->service->delete($evaluation);

        return response()->json([
            'message' => __('evaluations.deleted_successfully'),
        ]);
    }

    public function sendAbsenceAlerts(Evaluation $evaluation): JsonResponse
    {
        $this->dataScope->abortUnlessCanAccessEvaluation($evaluation);

        $result = $this->service->sendAbsenceAlerts($evaluation);
        $hasErrors = $result['errors'] !== [];
        $message = ($result['local_preview'] ?? false)
            ? __('evaluations.absence_alerts_preview_created')
            : __('evaluations.absence_alerts_sent_successfully');

        if ($hasErrors) {
            return response()->json([
                'message' => __('evaluations.absence_alerts_completed_with_errors'),
                'meta' => $result,
            ], 422);
        }

        return response()->json([
            'message' => $message,
            'meta' => $result,
        ]);
    }

    public function absenceAlertPreviews(Evaluation $evaluation): JsonResponse
    {
        $this->dataScope->abortUnlessCanAccessEvaluation($evaluation);

        return response()->json([
            'data' => $this->service->absenceAlertPreviews($evaluation),
        ]);
    }

    public function messageLogs(Evaluation $evaluation): JsonResponse
    {
        $this->dataScope->abortUnlessCanAccessEvaluation($evaluation);

        return response()->json([
            'data' => $this->service->absenceMessageLogs($evaluation),
        ]);
    }

    public function resendMessageLog(Evaluation $evaluation, AbsenceRuleExecutionLog $log): JsonResponse
    {
        $this->dataScope->abortUnlessCanAccessEvaluation($evaluation);

        return response()->json([
            'message' => __('evaluations.absence_message_resent_successfully'),
            'data' => $this->service->resendAbsenceMessage($evaluation, $log),
        ]);
    }
}
