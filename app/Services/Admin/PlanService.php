<?php

namespace App\Services\Admin;

use App\Imports\PlanPointsImport;
use App\Models\Plan;
use App\Services\System\DateTimeFormatterService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Facades\Excel;

class PlanService
{
    public function __construct(
        private readonly DateTimeFormatterService $dateTimeFormatter,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     */
    public function list(array $filters): LengthAwarePaginator
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $perPage = (int) ($filters['per_page'] ?? 50);
        $sortBy = (string) ($filters['sort_by'] ?? 'id');
        $sortDir = (string) ($filters['sort_dir'] ?? 'asc');
        $allowedSorts = ['id', 'name', 'category', 'created_at'];

        $sortBy = in_array($sortBy, $allowedSorts, true) ? $sortBy : 'id';
        $sortDir = in_array($sortDir, ['asc', 'desc'], true) ? $sortDir : 'asc';

        $query = Plan::query()
            ->select(['id', 'name', 'category', 'created_at'])
            ->withCount('points')
            ->when($search !== '', function ($query) use ($search): void {
                $query->where('name', 'like', "%{$search}%");
            })
            ->orderBy($sortBy, $sortDir)
            ->when($sortBy !== 'id', fn ($query) => $query->orderBy('id'));

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
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Plan
    {
        return Plan::query()->create([
            'name' => $data['name'],
            'category' => $data['category'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Plan $plan, array $data): Plan
    {
        $plan->update([
            'name' => $data['name'],
            'category' => $data['category'],
        ]);

        return $plan->refresh();
    }

    public function delete(Plan $plan): void
    {
        $plan->delete();
    }

    public function pointRows(Plan $plan): Collection
    {
        return $plan->points()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get([
                'id',
                'sort_order',
                'name',
                'points',
                'weight',
                'is_standalone',
                'requires_certificate',
                'surah_name',
                'part_name',
                'three_parts',
            ]);
    }

    /**
     * @return array{imported: int, skipped: int, errors: array<int, string>}
     */
    public function importPointFile(Plan $plan, UploadedFile $file): array
    {
        $import = new PlanPointsImport($plan);
        $previousErrorReporting = error_reporting();

        try {
            error_reporting($previousErrorReporting & ~E_DEPRECATED);
            Excel::import($import, $file);
        } finally {
            error_reporting($previousErrorReporting);
        }

        return $import->result();
    }
}
