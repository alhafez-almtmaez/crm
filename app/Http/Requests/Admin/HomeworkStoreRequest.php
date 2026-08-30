<?php

namespace App\Http\Requests\Admin;

use App\Models\Group;
use App\Models\Homework;
use App\Services\Admin\AdminDataScopeService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class HomeworkStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'center_id' => filled($this->input('center_id')) ? (int) $this->input('center_id') : null,
            'group_id' => (int) $this->input('group_id'),
            'date' => (string) $this->input('date'),
            'items' => $this->normalizedItems(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $dataScope = app(AdminDataScopeService::class);
        $groupId = $this->rowGroupId();
        $homework = $this->route('homework');
        $homeworkId = $homework instanceof Homework ? (int) $homework->id : null;
        $studentRule = Rule::exists('students', 'id')
            ->where(function ($query) use ($dataScope, $groupId, $homeworkId): void {
                $query->where(function ($allowed) use ($groupId, $homeworkId): void {
                    if ($groupId !== null) {
                        $allowed->whereExists(function ($membership) use ($groupId): void {
                            $membership
                                ->selectRaw('1')
                                ->from('group_student')
                                ->whereColumn('group_student.student_id', 'students.id')
                                ->where('group_student.group_id', $groupId);
                        });
                    }

                    if ($homeworkId !== null) {
                        $method = $groupId !== null ? 'orWhereExists' : 'whereExists';
                        $allowed->{$method}(function ($historical) use ($homeworkId): void {
                            $historical
                                ->selectRaw('1')
                                ->from('homework_students')
                                ->whereColumn('homework_students.student_id', 'students.id')
                                ->where('homework_students.homework_id', $homeworkId);
                        });
                    }
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
                    ->where(fn ($query) => $dataScope->applyGroupAccess($query, 'groups')),
            ],
            'date' => [
                'required',
                'date_format:Y-m-d',
                Rule::unique('homeworks', 'date')
                    ->where(fn ($query) => $query->where('group_id', $groupId)),
            ],
            'items' => ['required', 'array', 'min:1'],
            'items.*.student_id' => ['required', 'distinct', $studentRule],
            'items.*.points_adjustment' => ['nullable', 'integer', 'min:-1000000', 'max:1000000'],
            'items.*.points' => ['array'],
            'items.*.points.*.plan_point_id' => ['required', Rule::exists('plan_points', 'id')],
            'items.*.points.*.is_done' => ['boolean'],
            'items.*.points.*.is_next_homework' => ['boolean'],
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
                ->find($this->rowGroupId());
            if ($group === null || $this->dateMatchesGroupWorkingDays($group, (string) $this->input('date'))) {
                return;
            }

            $validator->errors()->add('date', __('homeworks.date_not_in_group_working_days'));
        });
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function normalizedItems(): array
    {
        $items = $this->input('items', []);
        if (! is_array($items)) {
            return [];
        }

        return array_map(static function ($row): array {
            if (! is_array($row)) {
                return [];
            }

            $points = $row['points'] ?? [];
            if (! is_array($points)) {
                $points = [];
            }

            return [
                'student_id' => isset($row['student_id']) ? (int) $row['student_id'] : null,
                'points_adjustment' => isset($row['points_adjustment']) ? (int) $row['points_adjustment'] : 0,
                'points' => array_map(static fn ($point): array => [
                    'plan_point_id' => isset($point['plan_point_id']) ? (int) $point['plan_point_id'] : null,
                    'is_done' => filter_var($point['is_done'] ?? false, FILTER_VALIDATE_BOOLEAN),
                    'is_next_homework' => filter_var($point['is_next_homework'] ?? false, FILTER_VALIDATE_BOOLEAN),
                ], array_filter($points, static fn ($point): bool => is_array($point))),
            ];
        }, $items);
    }

    protected function rowGroupId(): ?int
    {
        $groupId = (int) $this->input('group_id');

        return $groupId > 0 ? $groupId : null;
    }

    private function dateMatchesGroupWorkingDays(Group $group, string $date): bool
    {
        $workingDays = is_array($group->working_days) ? $group->working_days : [];
        if ($workingDays === []) {
            return true;
        }

        $lookup = array_fill_keys(array_map(
            static fn (string $day): string => strtolower($day),
            array_filter($workingDays, static fn ($day): bool => is_string($day) && trim($day) !== ''),
        ), true);

        if ($lookup === []) {
            return true;
        }

        $dayNames = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];
        $dayName = $dayNames[Carbon::parse($date)->dayOfWeek] ?? '';

        return isset($lookup[$dayName]);
    }
}
