<?php

namespace App\Services\Admin;

use App\Models\Plan;
use App\Services\System\DateTimeFormatterService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class PlanService
{
    public function __construct(private readonly DateTimeFormatterService $dateTimeFormatter)
    {
    }

    /**
     * @param array<string, mixed> $filters
     */
    public function list(array $filters): LengthAwarePaginator
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $perPage = (int) ($filters['per_page'] ?? 10);
        $sortBy = (string) ($filters['sort_by'] ?? 'id');
        $sortDir = (string) ($filters['sort_dir'] ?? 'desc');
        $allowedSorts = ['id', 'name', 'created_at'];

        $sortBy = in_array($sortBy, $allowedSorts, true) ? $sortBy : 'id';
        $sortDir = in_array($sortDir, ['asc', 'desc'], true) ? $sortDir : 'desc';

        $query = Plan::query()
            ->select(['id', 'name', 'created_at'])
            ->when($search !== '', function ($query) use ($search): void {
                $query->where('name', 'like', "%{$search}%");
            })
            ->orderBy($sortBy, $sortDir);

        $plans = $query->paginate($perPage)->withQueryString();
        $plans->setCollection(
            $plans->getCollection()->map(function (Plan $plan): Plan {
                $plan->setAttribute('created_at_formatted', $this->dateTimeFormatter->formatForAdmin($plan->created_at));

                return $plan;
            }),
        );

        return $plans;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function create(array $data): Plan
    {
        return Plan::query()->create([
            'name' => $data['name'],
        ]);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(Plan $plan, array $data): Plan
    {
        $plan->update([
            'name' => $data['name'],
        ]);

        return $plan->refresh();
    }

    public function delete(Plan $plan): void
    {
        $plan->delete();
    }
}
