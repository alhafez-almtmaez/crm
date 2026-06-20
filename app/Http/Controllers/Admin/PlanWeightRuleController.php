<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PlanWeightRuleIndexRequest;
use App\Http\Requests\Admin\PlanWeightRuleStoreRequest;
use App\Http\Requests\Admin\PlanWeightRuleUpdateRequest;
use App\Models\PlanWeightRule;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;
use Inertia\Response;

class PlanWeightRuleController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('can:plan_weight_rules.view', only: ['index', 'records']),
            new Middleware('can:plan_weight_rules.create', only: ['store']),
            new Middleware('can:plan_weight_rules.update', only: ['update']),
            new Middleware('can:plan_weight_rules.delete', only: ['destroy']),
        ];
    }

    public function index(): Response
    {
        return Inertia::render('Admin/PlanWeightRules');
    }

    public function records(PlanWeightRuleIndexRequest $request): JsonResponse
    {
        $filters = $request->validated();
        $search = trim((string) ($filters['search'] ?? ''));
        $perPage = (int) ($filters['per_page'] ?? 25);
        $sortBy = (string) ($filters['sort_by'] ?? 'priority');
        $sortDir = (string) ($filters['sort_dir'] ?? 'desc');

        $rules = PlanWeightRule::query()
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($builder) use ($search): void {
                    $builder
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('pattern', 'like', "%{$search}%")
                        ->orWhere('keyword', 'like', "%{$search}%")
                        ->orWhere('classification', 'like', "%{$search}%");
                });
            })
            ->orderBy($sortBy, $sortDir)
            ->orderBy('id')
            ->paginate($perPage)
            ->withQueryString();

        return response()->json([
            'data' => $rules->items(),
            'meta' => [
                'current_page' => $rules->currentPage(),
                'per_page' => $rules->perPage(),
                'total' => $rules->total(),
            ],
        ]);
    }

    public function store(PlanWeightRuleStoreRequest $request): JsonResponse
    {
        $rule = PlanWeightRule::query()->create($request->validated());

        return response()->json([
            'message' => __('plan_weight_rules.created_successfully'),
            'data' => $rule,
        ], 201);
    }

    public function update(PlanWeightRuleUpdateRequest $request, PlanWeightRule $planWeightRule): JsonResponse
    {
        $planWeightRule->update($request->validated());

        return response()->json([
            'message' => __('plan_weight_rules.updated_successfully'),
            'data' => $planWeightRule->refresh(),
        ]);
    }

    public function destroy(PlanWeightRule $planWeightRule): JsonResponse
    {
        $planWeightRule->delete();

        return response()->json([
            'message' => __('plan_weight_rules.deleted_successfully'),
        ]);
    }
}
