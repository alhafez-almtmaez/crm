<?php

namespace App\Services\Admin;

use App\Models\Center;
use App\Services\System\DateTimeFormatterService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;

class CenterService
{
    public function __construct(
        private readonly DateTimeFormatterService $dateTimeFormatter,
        private readonly AdminDataScopeService $dataScope,
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
        $allowedSorts = ['id', 'name', 'student_gender', 'phone', 'created_at'];

        $sortBy = in_array($sortBy, $allowedSorts, true) ? $sortBy : 'id';
        $sortDir = in_array($sortDir, ['asc', 'desc'], true) ? $sortDir : 'asc';

        $query = Center::query()
            ->active()
            ->select([
                'id',
                'name',
                'certificate_name',
                'student_gender',
                'phone',
                'show_center_manager_signature',
                'created_at',
            ])
            ->tap(fn ($query) => $this->dataScope->applyCenterAccess($query, 'centers'))
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($builder) use ($search): void {
                    $builder
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('student_gender', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
                });
            })
            ->orderBy($sortBy, $sortDir);

        $centers = $query->paginate($perPage)->withQueryString();
        $centers->setCollection(
            $centers->getCollection()->map(function (Center $center): Center {
                $center->setAttribute('created_at_formatted', $this->dateTimeFormatter->formatForAdmin($center->created_at));

                return $center;
            }),
        );

        return $centers;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Center
    {
        return Center::query()->create([
            'name' => $data['name'],
            'certificate_name' => $data['certificate_name'] ?? null,
            'student_gender' => $data['student_gender'],
            'phone' => $data['phone'],
            // These legacy columns remain only as a compatibility fallback.
            // Group messaging and schedules are configured on each group.
            'group_serialized' => null,
            'working_days' => [],
            'show_center_manager_signature' => (bool) ($data['show_center_manager_signature'] ?? true),
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Center $center, array $data): Center
    {
        $center->update([
            'name' => $data['name'],
            'certificate_name' => $data['certificate_name'] ?? null,
            'student_gender' => $data['student_gender'],
            'phone' => $data['phone'],
            'show_center_manager_signature' => (bool) ($data['show_center_manager_signature'] ?? true),
        ]);

        return $center->refresh();
    }

    public function delete(Center $center): void
    {
        if ($center->groups()->exists()) {
            throw ValidationException::withMessages([
                'center' => __('centers.cannot_delete_with_groups'),
            ]);
        }

        $center->delete();
    }
}
