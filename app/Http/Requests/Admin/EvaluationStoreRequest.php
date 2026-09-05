<?php

namespace App\Http\Requests\Admin;

use App\Models\Evaluation;
use App\Models\EvaluationStudent;
use App\Models\Group;
use App\Services\Admin\AdminDataScopeService;
use App\Support\GroupMonthlyPlanCoverage;
use App\Support\GroupWorkingDays;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class EvaluationStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $items = $this->input('items', []);
        if (! is_array($items)) {
            $items = [];
        }

        $normalizedItems = array_map(static function ($row): array {
            if (! is_array($row)) {
                return [];
            }

            $toNullableInt = static function (mixed $value): ?int {
                if ($value === null || $value === '') {
                    return null;
                }

                return (int) $value;
            };

            return [
                'student_id' => isset($row['student_id']) ? (int) $row['student_id'] : null,
                'attendances' => isset($row['attendances']) ? (int) $row['attendances'] : EvaluationStudent::ATTENDANCE_PRESENT,
                'alhifz' => $toNullableInt($row['alhifz'] ?? null),
                'warud' => $toNullableInt($row['warud'] ?? null),
                'akhlaqi' => $toNullableInt($row['akhlaqi'] ?? null),
                'tajwid' => $toNullableInt($row['tajwid'] ?? null),
                'note' => isset($row['note']) ? trim((string) $row['note']) : null,
            ];
        }, $items);

        $this->merge([
            'center_id' => filled($this->input('center_id')) ? (int) $this->input('center_id') : null,
            'group_id' => (int) $this->input('group_id'),
            'evaluation_type' => (int) $this->input('evaluation_type', Evaluation::TYPE_ALHIFZ),
            'date' => (string) $this->input('date'),
            'items' => $normalizedItems,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $dataScope = app(AdminDataScopeService::class);
        $groupId = (int) $this->input('group_id');
        $studentRule = Rule::exists('students', 'id')
            ->where(function ($query) use ($groupId, $dataScope): void {
                $query->whereExists(function ($membership) use ($groupId): void {
                    $membership
                        ->selectRaw('1')
                        ->from('group_student')
                        ->whereColumn('group_student.student_id', 'students.id')
                        ->where('group_student.group_id', $groupId);
                });
                $dataScope->applyStudentAccess($query, 'students');
            });

        return [
            'center_id' => [
                'nullable',
                'integer',
                Rule::exists('centers', 'id')
                    ->where(function ($query) use ($dataScope): void {
                        $query->whereNull('archived_at');
                        $dataScope->applyCenterAccess($query, 'centers');
                    }),
            ],
            'group_id' => [
                'required',
                'integer',
                Rule::exists('groups', 'id')
                    ->where(function ($query) use ($dataScope): void {
                        $query->whereExists(function ($centers): void {
                            $centers
                                ->selectRaw('1')
                                ->from('centers')
                                ->whereColumn('centers.id', 'groups.center_id')
                                ->whereNull('centers.archived_at');
                        });
                        $dataScope->applyGroupAccess($query, 'groups');
                    }),
            ],
            'evaluation_type' => ['required', 'integer', Rule::in([Evaluation::TYPE_ALHIFZ, Evaluation::TYPE_TAJWID])],
            'date' => [
                'required',
                'date_format:Y-m-d',
                Rule::unique('evaluations', 'date')
                    ->where(fn ($query) => $query->where('group_id', $groupId)),
            ],
            'items' => ['required', 'array', 'min:1'],
            'items.*.student_id' => ['required', 'distinct', $studentRule],
            'items.*.attendances' => ['required', Rule::in([
                EvaluationStudent::ATTENDANCE_PRESENT,
                EvaluationStudent::ATTENDANCE_EXCUSED_ABSENCE,
                EvaluationStudent::ATTENDANCE_ABSENCE,
                EvaluationStudent::ATTENDANCE_EXEMPT,
                EvaluationStudent::ATTENDANCE_LATE,
            ])],
            'items.*.alhifz' => ['nullable', 'integer', 'min:0', 'max:10'],
            'items.*.warud' => ['nullable', 'integer', 'min:0', 'max:10'],
            'items.*.akhlaqi' => ['nullable', 'integer', 'min:0', 'max:10'],
            'items.*.tajwid' => ['nullable', 'integer', 'min:0', 'max:10'],
            'items.*.note' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->has('group_id') || $validator->errors()->has('date')) {
                return;
            }

            $group = Group::query()
                ->tap(fn ($query) => app(AdminDataScopeService::class)->applyGroupAccess($query, 'groups'))
                ->find((int) $this->input('group_id'), ['id', 'working_days']);
            if ($group === null) {
                return;
            }

            if (! GroupWorkingDays::isConfigured($group->working_days)) {
                $validator->errors()->add('date', __('groups.working_days_not_configured'));

                return;
            }

            if (! GroupWorkingDays::includes($group->working_days, (string) $this->input('date'))) {
                $validator->errors()->add('date', __('evaluations.date_not_in_group_working_days'));

                return;
            }

            if (! GroupMonthlyPlanCoverage::exists((int) $group->id, (string) $this->input('date'))) {
                $validator->errors()->add('date', __('monthly_plans.required_for_follow_up_date'));
            }
        });
    }
}
