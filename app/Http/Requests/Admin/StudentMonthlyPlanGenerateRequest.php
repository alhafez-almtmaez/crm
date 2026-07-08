<?php

namespace App\Http\Requests\Admin;

use App\Services\Admin\AdminDataScopeService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Throwable;

class StudentMonthlyPlanGenerateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $month = (int) $this->input('month', now()->month);
        $year = (int) $this->input('year', now()->year);
        $defaultMonth = $month >= 1 && $month <= 12 ? $month : now()->month;
        $defaultYear = $year >= 2020 && $year <= 2100 ? $year : now()->year;
        $monthStart = CarbonImmutable::create($defaultYear, $defaultMonth, 1)->startOfDay();

        $this->merge([
            'center_id' => (int) $this->input('center_id'),
            'group_id' => $this->filled('group_id') ? (int) $this->input('group_id') : null,
            'month' => $month,
            'year' => $year,
            'start_date' => $this->input('start_date') ?: $monthStart->toDateString(),
            'end_date' => $this->input('end_date') ?: $monthStart->endOfMonth()->toDateString(),
            'holiday_dates' => is_array($this->input('holiday_dates')) ? array_values($this->input('holiday_dates')) : [],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $dataScope = app(AdminDataScopeService::class);
        $centerRule = Rule::exists('centers', 'id')
            ->where(fn ($query) => $dataScope->applyCenterAccess($query, 'centers'));
        $groupRule = Rule::exists('groups', 'id')
            ->where(function ($query) use ($dataScope): void {
                $query->where('center_id', (int) $this->input('center_id'));
                $dataScope->applyGroupAccess($query, 'groups');
            });

        return [
            'center_id' => ['required', $centerRule],
            'group_id' => [
                'nullable',
                $groupRule,
            ],
            'month' => ['required', 'integer', 'min:1', 'max:12'],
            'year' => ['required', 'integer', 'min:2020', 'max:2100'],
            'start_date' => ['required', 'date_format:Y-m-d'],
            'end_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'holiday_dates' => ['nullable', 'array'],
            'holiday_dates.*' => ['required', 'date_format:Y-m-d', 'distinct'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (
                $validator->errors()->has('month')
                || $validator->errors()->has('year')
                || $validator->errors()->has('start_date')
                || $validator->errors()->has('end_date')
            ) {
                return;
            }

            $month = (int) $this->input('month');
            $year = (int) $this->input('year');
            $startDate = null;
            $endDate = null;

            foreach (['start_date', 'end_date'] as $field) {
                try {
                    $date = CarbonImmutable::createFromFormat('Y-m-d', (string) $this->input($field))->startOfDay();
                } catch (Throwable) {
                    continue;
                }

                if ((int) $date->month !== $month || (int) $date->year !== $year) {
                    $validator->errors()->add($field, __('monthly_plans.date_must_match_selected_month', [
                        'attribute' => $this->attributes()[$field],
                    ]));
                }

                if ($field === 'start_date') {
                    $startDate = $date;
                }

                if ($field === 'end_date') {
                    $endDate = $date;
                }
            }

            if ($startDate === null || $endDate === null || $startDate->gt($endDate)) {
                return;
            }

            foreach ((array) $this->input('holiday_dates', []) as $index => $holidayDate) {
                try {
                    $date = CarbonImmutable::createFromFormat('Y-m-d', (string) $holidayDate)->startOfDay();
                } catch (Throwable) {
                    continue;
                }

                if ($date->lt($startDate) || $date->gt($endDate)) {
                    $validator->errors()->add("holiday_dates.{$index}", __('monthly_plans.date_must_be_within_plan_period', [
                        'attribute' => __('monthly_plans.holiday_dates'),
                    ]));
                }
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'center_id' => __('monthly_plans.center'),
            'group_id' => __('monthly_plans.group'),
            'month' => __('monthly_plans.month'),
            'year' => __('monthly_plans.year'),
            'start_date' => __('monthly_plans.start_date'),
            'end_date' => __('monthly_plans.end_date'),
            'holiday_dates' => __('monthly_plans.holiday_dates'),
            'holiday_dates.*' => __('monthly_plans.holiday_dates'),
        ];
    }
}
