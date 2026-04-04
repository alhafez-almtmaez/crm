<?php

namespace App\Services\Admin;

use App\Models\Center;
use App\Services\System\DateTimeFormatterService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class CenterService
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
        $allowedSorts = ['id', 'name', 'phone', 'created_at'];

        $sortBy = in_array($sortBy, $allowedSorts, true) ? $sortBy : 'id';
        $sortDir = in_array($sortDir, ['asc', 'desc'], true) ? $sortDir : 'desc';

        $query = Center::query()
            ->select(['id', 'name', 'phone', 'group_serialized', 'working_days', 'created_at'])
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($builder) use ($search): void {
                    $builder
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('group_serialized', 'like', "%{$search}%");
                });
            })
            ->orderBy($sortBy, $sortDir);

        $centers = $query->paginate($perPage)->withQueryString();
        $centers->setCollection(
            $centers->getCollection()->map(function (Center $center): Center {
                $days = is_array($center->working_days) ? $center->working_days : [];
                $dayLabels = array_map(static fn (string $day): string => __('days.'.$day), $days);
                $center->setAttribute('working_days_display', implode(', ', $dayLabels));
                $center->setAttribute('created_at_formatted', $this->dateTimeFormatter->formatForAdmin($center->created_at));

                return $center;
            }),
        );

        return $centers;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function create(array $data): Center
    {
        return Center::query()->create([
            'name' => $data['name'],
            'phone' => $data['phone'],
            'group_serialized' => $data['group_serialized'] ?? null,
            'working_days' => $data['working_days'],
        ]);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(Center $center, array $data): Center
    {
        $center->update([
            'name' => $data['name'],
            'phone' => $data['phone'],
            'group_serialized' => $data['group_serialized'] ?? null,
            'working_days' => $data['working_days'],
        ]);

        return $center->refresh();
    }

    public function delete(Center $center): void
    {
        $center->delete();
    }
}
