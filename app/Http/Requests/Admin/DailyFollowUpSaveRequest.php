<?php

namespace App\Http\Requests\Admin;

use App\Models\Evaluation;
use App\Models\EvaluationStudent;
use App\Models\Group;
use App\Models\Homework;
use App\Services\Admin\AdminDataScopeService;
use App\Support\GroupMonthlyPlanCoverage;
use App\Support\GroupWorkingDays;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;
use Illuminate\Validation\Validator;

class DailyFollowUpSaveRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'center_id' => filled($this->input('center_id')) ? (int) $this->input('center_id') : null,
            'group_id' => filled($this->input('group_id')) ? (int) $this->input('group_id') : null,
            'date' => (string) $this->input('date'),
            'evaluation' => $this->normalizedEvaluation(),
            'homework' => $this->normalizedHomework(),
        ]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $scope = app(AdminDataScopeService::class);
        $groupId = (int) $this->input('group_id');
        $date = (string) $this->input('date');

        return [
            'center_id' => [
                'nullable',
                'integer',
                Rule::exists('centers', 'id')->where(function ($query) use ($scope): void {
                    $query->whereNull('archived_at');
                    $scope->applyCenterAccess($query, 'centers');
                }),
            ],
            'group_id' => [
                'required',
                'integer',
                Rule::exists('groups', 'id')->where(function ($query) use ($scope): void {
                    $query->whereExists(function ($centers): void {
                        $centers->selectRaw('1')
                            ->from('centers')
                            ->whereColumn('centers.id', 'groups.center_id')
                            ->whereNull('centers.archived_at');
                    });
                    $scope->applyGroupAccess($query, 'groups');
                }),
            ],
            'date' => ['required', 'date_format:Y-m-d'],
            'evaluation' => ['nullable', 'array'],
            'evaluation.evaluation_type' => [
                'required_with:evaluation',
                'integer',
                Rule::in([Evaluation::TYPE_ALHIFZ, Evaluation::TYPE_TAJWID]),
            ],
            'evaluation.items' => ['required_with:evaluation', 'array', 'min:1'],
            'evaluation.items.*.student_id' => [
                'required',
                'distinct',
                $this->studentRule(
                    $groupId,
                    $this->existingId(Evaluation::query(), $groupId, $date),
                    'evaluations_users',
                    'evaluation_id',
                ),
            ],
            'evaluation.items.*.attendances' => ['required', Rule::in([
                EvaluationStudent::ATTENDANCE_PRESENT,
                EvaluationStudent::ATTENDANCE_EXCUSED_ABSENCE,
                EvaluationStudent::ATTENDANCE_ABSENCE,
                EvaluationStudent::ATTENDANCE_EXEMPT,
            ])],
            'evaluation.items.*.alhifz' => ['nullable', 'integer', 'min:0', 'max:10'],
            'evaluation.items.*.warud' => ['nullable', 'integer', 'min:0', 'max:10'],
            'evaluation.items.*.akhlaqi' => ['nullable', 'integer', 'min:0', 'max:10'],
            'evaluation.items.*.tajwid' => ['nullable', 'integer', 'min:0', 'max:10'],
            'evaluation.items.*.note' => ['nullable', 'string', 'max:255'],
            'homework' => ['nullable', 'array'],
            'homework.items' => ['required_with:homework', 'array', 'min:1'],
            'homework.items.*.student_id' => [
                'required',
                'distinct',
                $this->studentRule(
                    $groupId,
                    $this->existingId(Homework::query(), $groupId, $date),
                    'homework_students',
                    'homework_id',
                ),
            ],
            'homework.items.*.points_adjustment' => ['nullable', 'integer', 'min:-1000000', 'max:1000000'],
            'homework.items.*.points' => ['array'],
            'homework.items.*.points.*.plan_point_id' => ['required', Rule::exists('plan_points', 'id')],
            'homework.items.*.points.*.is_done' => ['boolean'],
            'homework.items.*.points.*.is_next_homework' => ['boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->input('evaluation') === null && $this->input('homework') === null) {
                $validator->errors()->add('follow_up', __('daily_follow_up.nothing_to_save'));
            }

            $this->validateMutuallyExclusivePointStates($validator);

            if ($validator->errors()->has('group_id') || $validator->errors()->has('date')) {
                return;
            }

            $group = Group::query()
                ->tap(fn ($query) => app(AdminDataScopeService::class)->applyGroupAccess($query, 'groups'))
                ->find((int) $this->input('group_id'));
            if (! $group instanceof Group) {
                return;
            }

            $centerId = (int) $this->input('center_id');
            if ($centerId > 0 && $centerId !== (int) $group->center_id) {
                $validator->errors()->add('center_id', __('daily_follow_up.group_center_mismatch'));
            }

            $groupId = (int) $group->id;
            $date = (string) $this->input('date');
            $createsRecord = ($this->input('evaluation') !== null
                    && $this->existingId(Evaluation::query(), $groupId, $date) === null)
                || ($this->input('homework') !== null
                    && $this->existingId(Homework::query(), $groupId, $date) === null);
            if (! $createsRecord) {
                return;
            }

            if (! GroupWorkingDays::isConfigured($group->working_days)) {
                $validator->errors()->add('date', __('groups.working_days_not_configured'));

                return;
            }

            if (! GroupWorkingDays::includes($group->working_days, $date)) {
                $validator->errors()->add('date', __('homeworks.date_not_in_group_working_days'));

                return;
            }

            if (! GroupMonthlyPlanCoverage::exists($groupId, $date)) {
                $validator->errors()->add('date', __('monthly_plans.required_for_follow_up_date'));
            }
        });
    }

    /** @return array<string, mixed>|null */
    private function normalizedEvaluation(): ?array
    {
        $evaluation = $this->input('evaluation');
        if (! is_array($evaluation)) {
            return null;
        }

        $items = is_array($evaluation['items'] ?? null) ? $evaluation['items'] : [];

        return [
            'evaluation_type' => (int) ($evaluation['evaluation_type'] ?? Evaluation::TYPE_ALHIFZ),
            'items' => array_map(static function (mixed $row): array {
                if (! is_array($row)) {
                    return [];
                }

                $nullableInt = static fn (mixed $value): ?int => $value === null || $value === ''
                    ? null
                    : (int) $value;

                return [
                    'student_id' => isset($row['student_id']) ? (int) $row['student_id'] : null,
                    'attendances' => (int) ($row['attendances'] ?? EvaluationStudent::ATTENDANCE_PRESENT),
                    'alhifz' => $nullableInt($row['alhifz'] ?? null),
                    'warud' => $nullableInt($row['warud'] ?? null),
                    'akhlaqi' => $nullableInt($row['akhlaqi'] ?? null),
                    'tajwid' => $nullableInt($row['tajwid'] ?? null),
                    'note' => isset($row['note']) ? trim((string) $row['note']) : null,
                ];
            }, $items),
        ];
    }

    /** @return array<string, mixed>|null */
    private function normalizedHomework(): ?array
    {
        $homework = $this->input('homework');
        if (! is_array($homework)) {
            return null;
        }

        $items = is_array($homework['items'] ?? null) ? $homework['items'] : [];

        return [
            'items' => array_map(static function (mixed $row): array {
                if (! is_array($row)) {
                    return [];
                }

                $points = is_array($row['points'] ?? null) ? $row['points'] : [];

                return [
                    'student_id' => isset($row['student_id']) ? (int) $row['student_id'] : null,
                    'points_adjustment' => isset($row['points_adjustment']) ? (int) $row['points_adjustment'] : 0,
                    'points' => array_map(static fn (array $point): array => [
                        'plan_point_id' => isset($point['plan_point_id']) ? (int) $point['plan_point_id'] : null,
                        'is_done' => filter_var($point['is_done'] ?? false, FILTER_VALIDATE_BOOLEAN),
                        'is_next_homework' => filter_var($point['is_next_homework'] ?? false, FILTER_VALIDATE_BOOLEAN),
                    ], array_values(array_filter($points, 'is_array'))),
                ];
            }, $items),
        ];
    }

    private function validateMutuallyExclusivePointStates(Validator $validator): void
    {
        foreach ($this->input('homework.items', []) as $itemIndex => $item) {
            if (! is_array($item)) {
                continue;
            }

            foreach ($item['points'] ?? [] as $pointIndex => $point) {
                if (
                    is_array($point)
                    && ($point['is_done'] ?? false) === true
                    && ($point['is_next_homework'] ?? false) === true
                ) {
                    $validator->errors()->add(
                        "homework.items.{$itemIndex}.points.{$pointIndex}.is_next_homework",
                        __('daily_follow_up.point_cannot_be_done_and_next'),
                    );
                }
            }
        }
    }

    private function studentRule(
        int $groupId,
        ?int $recordId,
        string $historyTable,
        string $historyForeignKey,
    ): Exists {
        $scope = app(AdminDataScopeService::class);

        return Rule::exists('students', 'id')->where(function ($query) use (
            $scope,
            $groupId,
            $recordId,
            $historyTable,
            $historyForeignKey,
        ): void {
            $query->where(function ($allowed) use ($groupId, $recordId, $historyTable, $historyForeignKey): void {
                $allowed->whereExists(function ($membership) use ($groupId): void {
                    $membership->selectRaw('1')
                        ->from('group_student')
                        ->whereColumn('group_student.student_id', 'students.id')
                        ->where('group_student.group_id', $groupId);
                });

                if ($recordId !== null) {
                    $allowed->orWhereExists(function ($historical) use ($recordId, $historyTable, $historyForeignKey): void {
                        $historical->selectRaw('1')
                            ->from($historyTable)
                            ->whereColumn("{$historyTable}.student_id", 'students.id')
                            ->where("{$historyTable}.{$historyForeignKey}", $recordId);
                    });
                }
            });

            $scope->applyStudentAccess($query, 'students');
        });
    }

    private function existingId(mixed $query, int $groupId, string $date): ?int
    {
        if ($groupId <= 0 || $date === '') {
            return null;
        }

        $id = $query->where('group_id', $groupId)->whereDate('date', $date)->value('id');

        return $id !== null ? (int) $id : null;
    }
}
