<?php

namespace App\Http\Requests\Admin;

use App\Models\MonthlyPlan;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use Throwable;

class StudentMonthlyPlanRefreshFutureRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'from_date' => $this->input('from_date'),
            'holiday_dates' => is_array($this->input('holiday_dates')) ? array_values($this->input('holiday_dates')) : [],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'from_date' => ['required', 'date_format:Y-m-d'],
            'holiday_dates' => ['nullable', 'array'],
            'holiday_dates.*' => ['required', 'date_format:Y-m-d', 'distinct'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->has('from_date')) {
                return;
            }

            $monthlyPlan = $this->route('monthlyPlan');
            if (! $monthlyPlan instanceof MonthlyPlan) {
                return;
            }

            try {
                $fromDate = CarbonImmutable::createFromFormat('Y-m-d', (string) $this->input('from_date'))->startOfDay();
            } catch (Throwable) {
                return;
            }

            $periodStart = $this->monthlyPlanDate($monthlyPlan->start_date)
                ?? CarbonImmutable::create((int) $monthlyPlan->year, (int) $monthlyPlan->month, 1)->startOfDay();
            $periodEnd = $this->monthlyPlanDate($monthlyPlan->end_date)
                ?? $periodStart->endOfMonth()->startOfDay();

            if ($fromDate->lt($periodStart) || $fromDate->gt($periodEnd)) {
                $validator->errors()->add('from_date', __('monthly_plans.date_must_be_within_plan_period', [
                    'attribute' => __('monthly_plans.refresh_from_date'),
                ]));
            }

            foreach ((array) $this->input('holiday_dates', []) as $index => $holidayDate) {
                try {
                    $date = CarbonImmutable::createFromFormat('Y-m-d', (string) $holidayDate)->startOfDay();
                } catch (Throwable) {
                    continue;
                }

                if ($date->lt($periodStart) || $date->gt($periodEnd)) {
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
            'from_date' => __('monthly_plans.refresh_from_date'),
            'holiday_dates' => __('monthly_plans.holiday_dates'),
            'holiday_dates.*' => __('monthly_plans.holiday_dates'),
        ];
    }

    private function monthlyPlanDate(mixed $value): ?CarbonImmutable
    {
        if ($value instanceof CarbonImmutable) {
            return $value->startOfDay();
        }

        if ($value instanceof DateTimeInterface) {
            return CarbonImmutable::instance($value)->startOfDay();
        }

        if (blank($value)) {
            return null;
        }

        return CarbonImmutable::parse((string) $value)->startOfDay();
    }
}
