<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AbsenceRuleIndexRequest;
use App\Http\Requests\Admin\AbsenceRuleStoreRequest;
use App\Http\Requests\Admin\AbsenceRuleUpdateRequest;
use App\Models\AbsenceRule;
use App\Services\Admin\AbsenceRuleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;
use Inertia\Response;

class AbsenceRuleController extends Controller implements HasMiddleware
{
    public function __construct(private readonly AbsenceRuleService $service)
    {
    }

    public static function middleware(): array
    {
        return [
            new Middleware('can:absence_rules.view', only: ['index', 'records']),
            new Middleware('can:absence_rules.create', only: ['create', 'store']),
            new Middleware('can:absence_rules.update', only: ['edit', 'update']),
            new Middleware('can:absence_rules.delete', only: ['destroy']),
        ];
    }

    public function index(): Response
    {
        return Inertia::render('Admin/AbsenceRules');
    }

    public function create(): Response
    {
        return Inertia::render('Admin/AbsenceRules/Create', [
            'centers' => $this->service->centerOptions(),
            'templates' => $this->service->templateOptions(),
        ]);
    }

    public function edit(AbsenceRule $absenceRule): Response
    {
        return Inertia::render('Admin/AbsenceRules/Edit', [
            'rule' => [
                'id' => $absenceRule->id,
                'center_id' => $absenceRule->center_id,
                'attendance_type' => $absenceRule->attendance_type,
                'occurrence_number' => $absenceRule->occurrence_number,
                'action' => $absenceRule->action,
                'message_template_id' => $absenceRule->message_template_id,
                'send_to_center_group' => $absenceRule->send_to_center_group,
                'freeze_reason' => $absenceRule->freeze_reason,
                'freeze_working_days_count' => $absenceRule->freeze_working_days_count,
                'deduction_points_count' => $absenceRule->deduction_points_count,
                'meta' => $absenceRule->meta,
                'is_active' => $absenceRule->is_active,
            ],
            'centers' => $this->service->centerOptions(),
            'templates' => $this->service->templateOptions(),
        ]);
    }

    public function records(AbsenceRuleIndexRequest $request): JsonResponse
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

    public function store(AbsenceRuleStoreRequest $request): RedirectResponse
    {
        $this->service->create($request->validated());

        return redirect()
            ->route('admin.absence-rules.index')
            ->with('success', __('absence_rules.created_successfully'));
    }

    public function update(AbsenceRuleUpdateRequest $request, AbsenceRule $absenceRule): RedirectResponse
    {
        $this->service->update($absenceRule, $request->validated());

        return redirect()
            ->route('admin.absence-rules.index')
            ->with('success', __('absence_rules.updated_successfully'));
    }

    public function destroy(AbsenceRule $absenceRule): JsonResponse
    {
        $this->service->delete($absenceRule);

        return response()->json([
            'message' => __('absence_rules.deleted_successfully'),
        ]);
    }
}
