<?php

namespace App\Http\Requests\Admin;

use App\Models\Center;
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
            'center_id' => (int) $this->input('center_id'),
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
        $centerId = $this->rowCenterId();
        $studentRule = Rule::exists('students', 'id')
            ->where(function ($query) use ($centerId, $dataScope): void {
                if ($centerId !== null) {
                    $query->where('center_id', $centerId);
                }

                $dataScope->applyStudentAccess($query, 'students');
            });

        return [
            'center_id' => [
                'required',
                Rule::exists('centers', 'id')
                    ->where(fn ($query) => $dataScope->applyCenterAccess($query, 'centers')),
            ],
            'date' => ['required', 'date_format:Y-m-d'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.student_id' => ['required', $studentRule],
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
            if ($validator->errors()->has('center_id') || $validator->errors()->has('date')) {
                return;
            }

            $center = Center::query()
                ->tap(fn ($query) => app(AdminDataScopeService::class)->applyCenterAccess($query, 'centers'))
                ->find((int) $this->input('center_id'));
            if ($center === null || $this->dateMatchesCenterWorkingDays($center, (string) $this->input('date'))) {
                return;
            }

            $validator->errors()->add('date', __('homeworks.date_not_in_center_working_days'));
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

    protected function rowCenterId(): ?int
    {
        $centerId = (int) $this->input('center_id');

        return $centerId > 0 ? $centerId : null;
    }

    private function dateMatchesCenterWorkingDays(Center $center, string $date): bool
    {
        $workingDays = is_array($center->working_days) ? $center->working_days : [];
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
