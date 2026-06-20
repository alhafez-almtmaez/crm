<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StudentMonthlyPlanGenerateRequest;
use App\Http\Requests\Admin\StudentMonthlyPlanIndexRequest;
use App\Models\Group;
use App\Services\Admin\StudentMonthlyPlanGenerator;
use App\Services\Admin\StudentMonthlyPlanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;
use Inertia\Response;

class StudentMonthlyPlanController extends Controller implements HasMiddleware
{
    public function __construct(
        private readonly StudentMonthlyPlanService $service,
        private readonly StudentMonthlyPlanGenerator $generator,
    ) {}

    public static function middleware(): array
    {
        return [
            new Middleware('can:monthly_plans.view', only: ['index', 'records']),
            new Middleware('can:monthly_plans.create', only: ['generate']),
        ];
    }

    public function index(): Response
    {
        return Inertia::render('Admin/MonthlyPlans', [
            'centers' => $this->service->centerOptions(),
            'groups' => $this->service->groupOptions(),
            'default_month' => now()->month,
            'default_year' => now()->year,
        ]);
    }

    public function records(StudentMonthlyPlanIndexRequest $request): JsonResponse
    {
        return response()->json([
            'data' => $this->service->list($request->validated()),
        ]);
    }

    public function generate(StudentMonthlyPlanGenerateRequest $request): JsonResponse
    {
        $data = $request->validated();
        $group = Group::query()->findOrFail((int) $data['group_id']);
        $result = $this->generator->generateForGroup($group, (int) $data['month'], (int) $data['year']);

        return response()->json([
            'message' => __('monthly_plans.generated_successfully', [
                'count' => $result['generated'],
            ]),
            'meta' => $result,
        ]);
    }
}
