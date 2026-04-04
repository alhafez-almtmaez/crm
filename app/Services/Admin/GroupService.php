<?php

namespace App\Services\Admin;

use App\Models\Center;
use App\Models\Group;
use App\Services\System\DateTimeFormatterService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class GroupService
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
        $allowedSorts = ['id', 'name', 'center_name', 'created_at'];

        $sortBy = in_array($sortBy, $allowedSorts, true) ? $sortBy : 'id';
        $sortDir = in_array($sortDir, ['asc', 'desc'], true) ? $sortDir : 'desc';
        $sortColumn = $sortBy === 'center_name' ? 'centers.name' : "groups.{$sortBy}";

        $query = Group::query()
            ->leftJoin('centers', 'groups.center_id', '=', 'centers.id')
            ->select(['groups.id', 'groups.name', 'groups.center_id', 'groups.created_at', 'centers.name as center_name'])
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($builder) use ($search): void {
                    $builder
                        ->where('groups.name', 'like', "%{$search}%")
                        ->orWhere('centers.name', 'like', "%{$search}%");
                });
            })
            ->orderBy($sortColumn, $sortDir);

        $groups = $query->paginate($perPage)->withQueryString();
        $groups->setCollection(
            $groups->getCollection()->map(function ($group) {
                $group->setAttribute('created_at_formatted', $this->dateTimeFormatter->formatForAdmin($group->created_at));

                return $group;
            }),
        );

        return $groups;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function create(array $data): Group
    {
        return Group::query()->create([
            'name' => $data['name'],
            'center_id' => (int) $data['center_id'],
        ]);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(Group $group, array $data): Group
    {
        $group->update([
            'name' => $data['name'],
            'center_id' => (int) $data['center_id'],
        ]);

        return $group->refresh();
    }

    public function delete(Group $group): void
    {
        $group->delete();
    }

    /**
     * @return array<int, array{id: int, name: string}>
     */
    public function centerOptions(): array
    {
        return Center::query()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(static fn (Center $center): array => [
                'id' => $center->id,
                'name' => $center->name,
            ])
            ->all();
    }

    /**
     * @return array<int, array{id: int, name: string}>
     */
    public function listByCenter(Center $center): array
    {
        return Group::query()
            ->where('center_id', $center->id)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(static fn (Group $group): array => [
                'id' => $group->id,
                'name' => $group->name,
            ])
            ->all();
    }
}
