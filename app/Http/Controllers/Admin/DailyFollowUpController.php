<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\DailyFollowUpSaveRequest;
use App\Http\Requests\Admin\EvaluationStoreRequest;
use App\Http\Requests\Admin\EvaluationUpdateRequest;
use App\Http\Requests\Admin\HomeworkStoreRequest;
use App\Http\Requests\Admin\HomeworkUpdateRequest;
use App\Models\Evaluation;
use App\Models\Group;
use App\Models\Homework;
use App\Models\Student;
use App\Services\Admin\AdminDataScopeService;
use App\Services\Admin\DailyFollowUpReportService;
use App\Services\Admin\EvaluationService;
use App\Services\Admin\HomeworkService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class DailyFollowUpController extends Controller implements HasMiddleware
{
    /** @var array<int, string> */
    private const ACCESS_PERMISSIONS = [
        'evaluations.view',
        'evaluations.create',
        'evaluations.update',
        'homeworks.view',
        'homeworks.create',
        'homeworks.update',
    ];

    public function __construct(
        private readonly EvaluationService $evaluationService,
        private readonly HomeworkService $homeworkService,
        private readonly DailyFollowUpReportService $reportService,
        private readonly AdminDataScopeService $dataScope,
    ) {}

    public static function middleware(): array
    {
        return [
            new Middleware('can:evaluations.create', only: ['storeEvaluation']),
            new Middleware('can:evaluations.update', only: ['updateEvaluation']),
            new Middleware('can:homeworks.create', only: ['storeHomework']),
            new Middleware('can:homeworks.update', only: ['updateHomework']),
        ];
    }

    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user !== null && $user->canAny(self::ACCESS_PERMISSIONS), 403);

        $query = $request->validate([
            'center_id' => [
                'nullable',
                'integer',
                Rule::exists('centers', 'id')
                    ->where(function ($query): void {
                        $query->whereNull('archived_at');
                        $this->dataScope->applyCenterAccess($query, 'centers');
                    }),
            ],
            'group_id' => [
                'nullable',
                'integer',
                Rule::exists('groups', 'id')
                    ->where(function ($query): void {
                        $query->whereExists(function ($centers): void {
                            $centers
                                ->selectRaw('1')
                                ->from('centers')
                                ->whereColumn('centers.id', 'groups.center_id')
                                ->whereNull('centers.archived_at');
                        });
                        $this->dataScope->applyGroupAccess($query, 'groups');
                    }),
            ],
            'date' => ['nullable', 'date_format:Y-m-d'],
            'evaluation_type' => ['nullable', 'integer', Rule::in([
                Evaluation::TYPE_ALHIFZ,
                Evaluation::TYPE_TAJWID,
            ])],
            'section' => ['nullable', Rule::in(['evaluation', 'homework'])],
        ]);

        $centers = $this->homeworkService->centerOptions();
        $centerId = isset($query['center_id']) ? (int) $query['center_id'] : null;
        $groupId = isset($query['group_id']) ? (int) $query['group_id'] : null;
        $date = isset($query['date']) ? (string) $query['date'] : null;

        [$centerId, $groupId] = $this->applySingleOptionDefaults($centers, $centerId, $groupId);

        // Homework owns the working-day rule. Resolve it first, then give the
        // evaluation the exact same canonical day so both panels never drift.
        $homeworkPayload = $this->homeworkService->createFormPayload($centerId, $groupId, $date);
        $selection = [
            'center_id' => $homeworkPayload['selected_center_id'],
            'group_id' => $homeworkPayload['selected_group_id'],
            'date' => $homeworkPayload['selected_date'],
        ];
        $evaluationPayload = $this->evaluationService->createFormPayload(
            $selection['center_id'],
            $selection['group_id'],
            $selection['date'],
        );

        $permissions = [
            'can_view_evaluations' => $user->can('evaluations.view'),
            'can_create_evaluation' => $user->can('evaluations.create'),
            'can_update_evaluation' => $user->can('evaluations.update'),
            'can_view_homeworks' => $user->can('homeworks.view'),
            'can_create_homework' => $user->can('homeworks.create'),
            'can_update_homework' => $user->can('homeworks.update'),
            'can_view_monthly_plans' => $user->can('monthly_plans.view'),
            'can_create_monthly_plans' => $user->can('monthly_plans.create'),
            'can_view_reports' => $user->can('evaluations.view')
                && $user->can('homeworks.view'),
        ];

        $evaluation = $this->evaluationPanelPayload(
            $evaluationPayload,
            (int) ($query['evaluation_type'] ?? Evaluation::TYPE_ALHIFZ),
            $permissions['can_view_evaluations'],
            $permissions['can_create_evaluation'],
            $permissions['can_update_evaluation'],
        );
        $homework = $this->homeworkPanelPayload(
            $homeworkPayload,
            $permissions['can_view_homeworks'],
            $permissions['can_create_homework'],
            $permissions['can_update_homework'],
        );
        $planContext = $this->reportService->workspacePlan(
            $selection['group_id'],
            $selection['date'],
            $user->can('homeworks.view'),
            $user->can('monthly_plans.view'),
        );

        return Inertia::render('Admin/DailyFollowUp', [
            'centers' => $centers,
            'selection' => $selection,
            'evaluation' => $evaluation,
            'homework' => $homework,
            'plan_context' => $planContext,
            'permissions' => $permissions,
            'date_adjustment' => $date !== null && $date !== $selection['date']
                ? ['from' => $date, 'to' => $selection['date']]
                : null,
            'active_section' => $this->resolveActiveSection(
                $query['section'] ?? null,
                $evaluation,
                $homework,
                $permissions,
            ),
        ]);
    }

    public function studentReport(Request $request, Student $student): JsonResponse
    {
        $user = $request->user();
        abort_unless(
            $user !== null
            && $user->can('evaluations.view')
            && $user->can('homeworks.view'),
            403,
        );

        $query = $request->validate([
            'group_id' => [
                'required',
                'integer',
                Rule::exists('groups', 'id')->where(function ($query): void {
                    $query->whereExists(function ($centers): void {
                        $centers
                            ->selectRaw('1')
                            ->from('centers')
                            ->whereColumn('centers.id', 'groups.center_id')
                            ->whereNull('centers.archived_at');
                    });
                    $this->dataScope->applyGroupAccess($query, 'groups');
                }),
            ],
            'date' => ['required', 'date_format:Y-m-d'],
        ]);

        $group = Group::query()->findOrFail((int) $query['group_id']);

        return response()->json($this->reportService->studentReport(
            $group,
            $student,
            (string) $query['date'],
            $user->can('monthly_plans.view'),
        ));
    }

    public function storeEvaluation(EvaluationStoreRequest $request): RedirectResponse
    {
        $evaluation = $this->evaluationService->create($request->validated());
        $date = $evaluation->date?->toDateString() ?? now()->toDateString();

        return $this->redirectToWorkspace(
            (int) $evaluation->center_id,
            (int) $evaluation->group_id,
            $date,
            'homework',
            __('evaluations.created_successfully'),
        );
    }

    public function updateEvaluation(EvaluationUpdateRequest $request, Evaluation $evaluation): RedirectResponse
    {
        $this->dataScope->abortUnlessCanAccessEvaluation($evaluation);
        $evaluation = $this->evaluationService->update($evaluation, $request->validated());

        return $this->redirectToWorkspace(
            (int) $evaluation->center_id,
            (int) $evaluation->group_id,
            $evaluation->date?->toDateString() ?? now()->toDateString(),
            'evaluation',
            __('evaluations.updated_successfully'),
        );
    }

    public function storeHomework(HomeworkStoreRequest $request): RedirectResponse
    {
        $homework = $this->homeworkService->create($request->validated());
        $date = $homework->date?->toDateString() ?? now()->toDateString();

        return $this->redirectToWorkspace(
            (int) $homework->center_id,
            (int) $homework->group_id,
            $date,
            'evaluation',
            __('homeworks.created_successfully'),
        );
    }

    public function updateHomework(HomeworkUpdateRequest $request, Homework $homework): RedirectResponse
    {
        $this->dataScope->abortUnlessCanAccessHomework($homework);
        $homework = $this->homeworkService->update($homework, $request->validated());

        return $this->redirectToWorkspace(
            (int) $homework->center_id,
            (int) $homework->group_id,
            $homework->date?->toDateString() ?? now()->toDateString(),
            'homework',
            __('homeworks.updated_successfully'),
        );
    }

    public function save(DailyFollowUpSaveRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $groupId = (int) $data['group_id'];
        $date = (string) $data['date'];
        $user = $request->user();
        abort_unless($user !== null, 403);

        $centerId = DB::transaction(function () use ($data, $date, $groupId, $user): int {
            $group = Group::query()
                ->tap(fn ($query) => $this->dataScope->applyGroupAccess($query, 'groups'))
                ->whereHas('center', fn ($query) => $query->whereNull('archived_at'))
                ->whereKey($groupId)
                ->lockForUpdate()
                ->firstOrFail(['id', 'center_id']);
            $centerId = (int) $group->center_id;

            $evaluationPayload = is_array($data['evaluation'] ?? null)
                ? $data['evaluation']
                : null;
            $homeworkPayload = is_array($data['homework'] ?? null)
                ? $data['homework']
                : null;

            $evaluation = $evaluationPayload !== null
                ? Evaluation::query()
                    ->where('group_id', $groupId)
                    ->whereDate('date', $date)
                    ->lockForUpdate()
                    ->first()
                : null;
            $homework = $homeworkPayload !== null
                ? Homework::query()
                    ->where('group_id', $groupId)
                    ->whereDate('date', $date)
                    ->lockForUpdate()
                    ->first()
                : null;

            if ($evaluationPayload !== null) {
                abort_unless($user->can($evaluation ? 'evaluations.update' : 'evaluations.create'), 403);
                if ($evaluation instanceof Evaluation) {
                    $this->dataScope->abortUnlessCanAccessEvaluation($evaluation);
                }
            }

            if ($homeworkPayload !== null) {
                abort_unless($user->can($homework ? 'homeworks.update' : 'homeworks.create'), 403);
                if ($homework instanceof Homework) {
                    $this->dataScope->abortUnlessCanAccessHomework($homework);
                }
            }

            if ($evaluationPayload !== null) {
                $payload = [
                    'center_id' => $centerId,
                    'group_id' => $groupId,
                    'date' => $date,
                    ...$evaluationPayload,
                ];

                if ($evaluation instanceof Evaluation) {
                    $this->evaluationService->update($evaluation, $payload);
                } else {
                    $this->evaluationService->create($payload);
                }
            }

            if ($homeworkPayload !== null) {
                $payload = [
                    'center_id' => $centerId,
                    'group_id' => $groupId,
                    'date' => $date,
                    ...$homeworkPayload,
                ];

                if ($homework instanceof Homework) {
                    $this->homeworkService->update($homework, $payload);
                } else {
                    $this->homeworkService->create($payload);
                }
            }

            return $centerId;
        });

        return $this->redirectToWorkspace(
            $centerId,
            $groupId,
            $date,
            'evaluation',
            __('daily_follow_up.saved_successfully'),
        );
    }

    /**
     * @param  array<int, array{id: int, name: string, groups: array<int, array<string, mixed>>}>  $centers
     * @return array{0: ?int, 1: ?int}
     */
    private function applySingleOptionDefaults(array $centers, ?int $centerId, ?int $groupId): array
    {
        if ($groupId !== null) {
            return [$centerId, $groupId];
        }

        if ($centerId === null && count($centers) === 1) {
            $centerId = (int) $centers[0]['id'];
        }

        if ($centerId === null) {
            return [null, null];
        }

        $center = collect($centers)->first(
            static fn (array $item): bool => (int) $item['id'] === $centerId,
        );
        $groups = is_array($center['groups'] ?? null) ? $center['groups'] : [];

        if (count($groups) === 1) {
            $groupId = (int) $groups[0]['id'];
        }

        return [$centerId, $groupId];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{id: ?int, evaluation_type: int, students: array<int, array<string, mixed>>, accessible: bool}
     */
    private function evaluationPanelPayload(
        array $payload,
        int $requestedType,
        bool $canView,
        bool $canCreate,
        bool $canUpdate,
    ): array {
        if (! $canView && ! $canCreate && ! $canUpdate) {
            return [
                'id' => null,
                'evaluation_type' => Evaluation::TYPE_ALHIFZ,
                'students' => [],
                'accessible' => false,
            ];
        }

        $evaluationId = isset($payload['existing_evaluation_id'])
            ? (int) $payload['existing_evaluation_id']
            : null;
        $evaluationType = $requestedType === Evaluation::TYPE_TAJWID
            ? Evaluation::TYPE_TAJWID
            : Evaluation::TYPE_ALHIFZ;
        $students = $canCreate && $evaluationId === null
            ? ($payload['students'] ?? [])
            : [];

        if ($evaluationId !== null) {
            $evaluation = Evaluation::query()->findOrFail($evaluationId);
            $accessible = $this->dataScope->canAccessEvaluation($evaluation);
            if (! $accessible) {
                return [
                    'id' => $evaluationId,
                    'evaluation_type' => $evaluationType,
                    'students' => [],
                    'accessible' => false,
                ];
            }

            $evaluationType = (int) ($evaluation->evaluation_type ?? Evaluation::TYPE_ALHIFZ);

            if ($canUpdate) {
                $students = $this->evaluationService->editStudentRows($evaluation);
            }
        }

        return [
            'id' => $evaluationId,
            'evaluation_type' => $evaluationType,
            'students' => $students,
            'accessible' => true,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{id: ?int, students: array<int, array<string, mixed>>, accessible: bool}
     */
    private function homeworkPanelPayload(
        array $payload,
        bool $canView,
        bool $canCreate,
        bool $canUpdate,
    ): array {
        if (! $canView && ! $canCreate && ! $canUpdate) {
            return [
                'id' => null,
                'students' => [],
                'accessible' => false,
            ];
        }

        $homeworkId = isset($payload['existing_homework_id'])
            ? (int) $payload['existing_homework_id']
            : null;
        $students = $canCreate && $homeworkId === null
            ? ($payload['students'] ?? [])
            : [];

        if ($homeworkId !== null) {
            $homework = Homework::query()->findOrFail($homeworkId);
            $accessible = $this->dataScope->canAccessHomework($homework);
            if (! $accessible) {
                return [
                    'id' => $homeworkId,
                    'students' => [],
                    'accessible' => false,
                ];
            }

            if ($canUpdate) {
                $students = $this->homeworkService->editStudentRows($homework);
            }
        }

        return [
            'id' => $homeworkId,
            'students' => $students,
            'accessible' => true,
        ];
    }

    /**
     * @param  array<string, mixed>  $evaluation
     * @param  array<string, mixed>  $homework
     * @param  array<string, bool>  $permissions
     */
    private function resolveActiveSection(
        mixed $requestedSection,
        array $evaluation,
        array $homework,
        array $permissions,
    ): string {
        $canAccessEvaluation = $permissions['can_view_evaluations']
            || $permissions['can_create_evaluation']
            || $permissions['can_update_evaluation'];
        $canAccessHomework = $permissions['can_view_homeworks']
            || $permissions['can_create_homework']
            || $permissions['can_update_homework'];

        if ($requestedSection === 'evaluation' && $canAccessEvaluation) {
            return 'evaluation';
        }

        if ($requestedSection === 'homework' && $canAccessHomework) {
            return 'homework';
        }

        if ($evaluation['id'] === null && $permissions['can_create_evaluation']) {
            return 'evaluation';
        }

        if ($homework['id'] === null && $permissions['can_create_homework']) {
            return 'homework';
        }

        return $canAccessEvaluation ? 'evaluation' : 'homework';
    }

    private function redirectToWorkspace(
        int $centerId,
        int $groupId,
        string $date,
        string $section,
        string $message,
    ): RedirectResponse {
        return redirect()
            ->route('admin.daily-follow-up.index', [
                'center_id' => $centerId,
                'group_id' => $groupId,
                'date' => $date,
                'section' => $section,
            ])
            ->with('success', $message);
    }
}
