<?php

namespace App\Services\Admin;

use App\Services\System\DateTimeFormatterService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Spatie\Activitylog\Models\Activity;

class ActivityLogService
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
        $allowedSorts = ['id', 'description', 'event', 'log_name', 'created_at'];

        $sortBy = in_array($sortBy, $allowedSorts, true) ? $sortBy : 'id';
        $sortDir = in_array($sortDir, ['asc', 'desc'], true) ? $sortDir : 'desc';

        $logs = Activity::query()
            ->select(['id', 'log_name', 'description', 'event', 'subject_type', 'subject_id', 'causer_type', 'causer_id', 'created_at'])
            ->with(['causer', 'subject'])
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($builder) use ($search): void {
                    $builder
                        ->where('description', 'like', "%{$search}%")
                        ->orWhere('event', 'like', "%{$search}%")
                        ->orWhere('log_name', 'like', "%{$search}%");
                });
            })
            ->orderBy($sortBy, $sortDir)
            ->paginate($perPage)
            ->withQueryString();

        $logs->setCollection(
            $logs->getCollection()->map(function (Activity $activity): Activity {
                $activity->setAttribute('causer_display', $this->formatCauser($activity));
                $activity->setAttribute('subject_display', $this->formatSubject($activity));
                $activity->setAttribute('created_at_formatted', $this->dateTimeFormatter->formatForAdmin($activity->created_at));

                return $activity;
            }),
        );

        return $logs;
    }

    /**
     * @return Collection<int, array{
     *     id: int,
     *     log_name: string|null,
     *     description: string,
     *     event: string|null,
     *     causer_display: string,
     *     subject_display: string,
     *     created_at_formatted: string,
     *     changes: array{old: array<string, mixed>, attributes: array<string, mixed>}
     * }>
     */
    public function listForSubject(string $subjectType, int|string $subjectId, int $limit = 50): Collection
    {
        return Activity::query()
            ->select(['id', 'log_name', 'description', 'event', 'subject_type', 'subject_id', 'causer_type', 'causer_id', 'attribute_changes', 'created_at'])
            ->with(['causer', 'subject'])
            ->where('subject_type', $subjectType)
            ->where('subject_id', $subjectId)
            ->latest('id')
            ->limit(max(1, min($limit, 100)))
            ->get()
            ->map(function (Activity $activity): array {
                $changes = $activity->attribute_changes;
                $old = is_array($changes['old'] ?? null) ? $changes['old'] : [];
                $attributes = is_array($changes['attributes'] ?? null) ? $changes['attributes'] : [];

                return [
                    'id' => (int) $activity->id,
                    'log_name' => $activity->log_name,
                    'description' => (string) $activity->description,
                    'event' => $activity->event,
                    'causer_display' => $this->formatCauser($activity),
                    'subject_display' => $this->formatSubject($activity),
                    'created_at_formatted' => $this->dateTimeFormatter->formatForAdmin($activity->created_at),
                    'changes' => [
                        'old' => $old,
                        'attributes' => $attributes,
                    ],
                ];
            });
    }

    private function formatCauser(Activity $activity): string
    {
        $causer = $activity->causer;
        if ($causer === null) {
            return '-';
        }

        $name = $causer->name ?? $causer->email ?? null;
        if (is_string($name) && $name !== '') {
            return $name;
        }

        return class_basename($activity->causer_type ?? 'User');
    }

    private function formatSubject(Activity $activity): string
    {
        $subject = $activity->subject;
        $subjectClass = class_basename($activity->subject_type ?? 'System');
        $subjectId = $activity->subject_id;

        if ($subject !== null) {
            $label = $subject->name ?? $subject->title ?? null;
            if (is_string($label) && $label !== '') {
                return $label;
            }
        }

        if ($subjectId !== null) {
            return "{$subjectClass} #{$subjectId}";
        }

        return $subjectClass;
    }
}
