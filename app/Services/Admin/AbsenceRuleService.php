<?php

namespace App\Services\Admin;

use App\Models\AbsenceRule;
use App\Models\Center;
use App\Models\MessageTemplate;
use App\Services\System\DateTimeFormatterService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class AbsenceRuleService
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

        $sortMap = [
            'id' => 'absence_rules.id',
            'center_name' => 'centers.name',
            'attendance_type' => 'absence_rules.attendance_type',
            'occurrence_number' => 'absence_rules.occurrence_number',
            'action' => 'absence_rules.action',
            'deduction_points_count' => 'absence_rules.deduction_points_count',
            'created_at' => 'absence_rules.created_at',
        ];
        $sortColumn = $sortMap[$sortBy] ?? 'absence_rules.id';
        $sortDir = in_array($sortDir, ['asc', 'desc'], true) ? $sortDir : 'desc';

        $query = AbsenceRule::query()
            ->leftJoin('centers', 'absence_rules.center_id', '=', 'centers.id')
            ->leftJoin('message_templates', 'absence_rules.message_template_id', '=', 'message_templates.id')
            ->select([
                'absence_rules.id',
                'absence_rules.center_id',
                'absence_rules.attendance_type',
                'absence_rules.occurrence_number',
                'absence_rules.action',
                'absence_rules.message_template_id',
                'absence_rules.send_to_center_group',
                'absence_rules.freeze_reason',
                'absence_rules.freeze_working_days_count',
                'absence_rules.deduction_points_count',
                'absence_rules.meta',
                'absence_rules.is_active',
                'absence_rules.created_at',
                'centers.name as center_name',
                'message_templates.name as message_template_name',
                'message_templates.key as message_template_key',
            ])
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($builder) use ($search): void {
                    $builder
                        ->where('centers.name', 'like', "%{$search}%")
                        ->orWhere('absence_rules.attendance_type', 'like', "%{$search}%")
                        ->orWhere('absence_rules.action', 'like', "%{$search}%")
                        ->orWhere('absence_rules.occurrence_number', 'like', "%{$search}%")
                        ->orWhere('absence_rules.deduction_points_count', 'like', "%{$search}%")
                        ->orWhere('message_templates.name', 'like', "%{$search}%");
                });
            })
            ->orderBy($sortColumn, $sortDir);

        $rules = $query->paginate($perPage)->withQueryString();
        $rules->setCollection(
            $rules->getCollection()->map(function ($row) {
                $row->setAttribute('created_at_formatted', $this->dateTimeFormatter->formatForAdmin($row->created_at));

                return $row;
            }),
        );

        return $rules;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function create(array $data): AbsenceRule
    {
        return AbsenceRule::query()->create($this->payload($data));
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(AbsenceRule $rule, array $data): AbsenceRule
    {
        $rule->update($this->payload($data));

        return $rule->refresh();
    }

    public function delete(AbsenceRule $rule): void
    {
        $rule->delete();
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
     * @return array<int, array{id: int, name: string, key: string}>
     */
    public function templateOptions(): array
    {
        return MessageTemplate::query()
            ->active()
            ->orderBy('name')
            ->get(['id', 'name', 'key'])
            ->map(static fn (MessageTemplate $template): array => [
                'id' => $template->id,
                'name' => $template->name,
                'key' => $template->key,
            ])
            ->all();
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function payload(array $data): array
    {
        $isFreezeAction = ($data['action'] ?? null) === AbsenceRule::ACTION_FREEZE_STUDENT;

        return [
            'center_id' => $data['center_id'] ?? null,
            'attendance_type' => $data['attendance_type'],
            'occurrence_number' => (int) $data['occurrence_number'],
            'action' => $data['action'],
            'message_template_id' => $data['message_template_id'] ?? null,
            'send_to_center_group' => (bool) ($data['send_to_center_group'] ?? false),
            'freeze_reason' => $isFreezeAction ? ($data['freeze_reason'] ?? null) : null,
            'freeze_working_days_count' => $isFreezeAction ? ($data['freeze_working_days_count'] ?? 4) : 4,
            'deduction_points_count' => max(0, (int) ($data['deduction_points_count'] ?? 0)),
            'meta' => $data['meta'] ?? null,
            'is_active' => (bool) ($data['is_active'] ?? true),
        ];
    }
}
