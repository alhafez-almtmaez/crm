<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StudentMonthlyPlanGenerateRequest;
use App\Http\Requests\Admin\StudentMonthlyPlanIndexRequest;
use App\Http\Requests\Admin\StudentMonthlyPlanRefreshFutureRequest;
use App\Models\Center;
use App\Models\Group;
use App\Models\MonthlyPlan;
use App\Services\Admin\AdminDataScopeService;
use App\Services\Admin\StudentMonthlyPlanGenerator;
use App\Services\Admin\StudentMonthlyPlanService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;
use Inertia\Response;

class StudentMonthlyPlanController extends Controller implements HasMiddleware
{
    public function __construct(
        private readonly StudentMonthlyPlanService $service,
        private readonly StudentMonthlyPlanGenerator $generator,
        private readonly AdminDataScopeService $dataScope,
    ) {}

    public static function middleware(): array
    {
        return [
            new Middleware('can:monthly_plans.view', only: ['index', 'records', 'edit']),
            new Middleware('can:monthly_plans.create', only: ['create', 'store']),
            new Middleware('can:monthly_plans.update', only: ['refreshFuture']),
            new Middleware('can:monthly_plans.delete', only: ['destroy']),
        ];
    }

    public function index(): Response
    {
        return Inertia::render('Admin/MonthlyPlans');
    }

    public function records(StudentMonthlyPlanIndexRequest $request): JsonResponse
    {
        $rows = $this->service->savedPlansPage($request->validated());

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
        $requestedGroupId = (int) $request->query('group_id', 0);
        $defaultGroup = $requestedGroupId > 0
            ? Group::query()
                ->tap(fn ($query) => $this->dataScope->applyGroupAccess($query, 'groups'))
                ->whereHas('center', fn ($query) => $query->whereNull('archived_at'))
                ->find($requestedGroupId, ['id', 'center_id'])
            : null;
        $requestedMonth = (int) $request->query('month', now()->month);
        $requestedYear = (int) $request->query('year', now()->year);
        $defaultMonth = $requestedMonth >= 1 && $requestedMonth <= 12 ? $requestedMonth : now()->month;
        $defaultYear = $requestedYear >= 2020 && $requestedYear <= 2100 ? $requestedYear : now()->year;
        $monthStart = CarbonImmutable::create($defaultYear, $defaultMonth, 1)->startOfDay();

        return Inertia::render('Admin/MonthlyPlans/Create', [
            'centers' => $this->service->centerOptions(),
            'groups' => $this->service->groupOptions(),
            'default_center_id' => $defaultGroup?->center_id,
            'default_group_id' => $defaultGroup?->id,
            'default_month' => $defaultMonth,
            'default_year' => $defaultYear,
            'default_start_date' => $monthStart->toDateString(),
            'default_end_date' => $monthStart->endOfMonth()->toDateString(),
        ]);
    }

    public function edit(MonthlyPlan $monthlyPlan): Response
    {
        $this->dataScope->abortUnlessCanAccessMonthlyPlan($monthlyPlan);

        return Inertia::render('Admin/MonthlyPlans/Edit', $this->service->savedPlanPayload($monthlyPlan));
    }

    public function report(string $publicId): Response
    {
        $report = $this->service->publicReportPayload($publicId);
        $plan = $report['monthly_plan'];
        $period = (string) ($plan['period_label'] ?? (((int) ($plan['month'] ?? 0)).'/'.((int) ($plan['year'] ?? 0))));
        $title = "الخطة الشهرية {$period} | مشروع الحافظ المتميز";
        $description = trim(($plan['center_name'] ?? '').' / '.($plan['group_name'] ?? '').' / '.$period);
        $imageUrl = asset('media/logos/logo.png');

        return Inertia::render('MonthlyPlans/PublicReport', [
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

    public function store(StudentMonthlyPlanGenerateRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $center = Center::query()->findOrFail((int) $data['center_id']);
        $this->dataScope->abortUnlessCanAccessCenter($center);

        $group = Group::query()
            ->where('center_id', $center->id)
            ->findOrFail((int) $data['group_id']);
        $this->dataScope->abortUnlessCanAccessGroup($group);

        $month = (int) $data['month'];
        $year = (int) $data['year'];
        $startDate = CarbonImmutable::createFromFormat('Y-m-d', (string) $data['start_date'])->startOfDay();
        $endDate = CarbonImmutable::createFromFormat('Y-m-d', (string) $data['end_date'])->startOfDay();
        $holidayDates = array_values((array) ($data['holiday_dates'] ?? []));

        $existingPlan = MonthlyPlan::query()
            ->where('group_id', $group->id)
            ->where('month', $month)
            ->where('year', $year)
            ->first();

        $result = $this->generator->generateForGroup($group, $month, $year, $startDate, $endDate, $holidayDates);

        $firstMonthlyPlanId = $result['monthly_plan_ids'][0] ?? null;
        if ($firstMonthlyPlanId !== null && count($result['monthly_plan_ids']) === 1) {
            $message = $existingPlan !== null && $result['generated'] === 0
                ? __('monthly_plans.already_exists')
                : __('monthly_plans.generated_successfully', [
                    'count' => $result['generated'],
                ]);

            return redirect()
                ->route('admin.monthly-plans.edit', $firstMonthlyPlanId)
                ->with('success', $message);
        }

        return redirect()
            ->route('admin.monthly-plans.index')
            ->with('success', __('monthly_plans.generated_successfully', [
                'count' => $result['generated'],
            ]));
    }

    public function refreshFuture(StudentMonthlyPlanRefreshFutureRequest $request, MonthlyPlan $monthlyPlan): RedirectResponse
    {
        $this->dataScope->abortUnlessCanAccessMonthlyPlan($monthlyPlan);

        $result = $this->generator->regenerateFutureForMonthlyPlan(
            monthlyPlan: $monthlyPlan,
            fromDate: CarbonImmutable::parse((string) $request->validated('from_date')),
            holidayDates: array_values((array) $request->validated('holiday_dates', [])),
        );

        return redirect()
            ->route('admin.monthly-plans.edit', $monthlyPlan)
            ->with('success', __('monthly_plans.future_refreshed_successfully', [
                'students' => $result['student_plans'],
                'items' => $result['generated_items'],
            ]));
    }

    public function destroy(MonthlyPlan $monthlyPlan): JsonResponse
    {
        $this->dataScope->abortUnlessCanAccessMonthlyPlan($monthlyPlan);

        $this->service->delete($monthlyPlan);

        return response()->json([
            'message' => __('monthly_plans.deleted_successfully'),
        ]);
    }
}
