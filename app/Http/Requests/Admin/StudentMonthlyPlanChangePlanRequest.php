<?php

namespace App\Http\Requests\Admin;

use App\Models\MonthlyPlan;
use App\Models\StudentMonthlyPlan;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Throwable;

class StudentMonthlyPlanChangePlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $startPointId = $this->input('starts_after_plan_point_id');

        $this->merge([
            'effective_date' => $this->input('effective_date'),
            'starts_after_plan_point_id' => $startPointId === '' ? null : $startPointId,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'effective_date' => ['required', 'date_format:Y-m-d'],
            'plan_id' => ['required', 'integer', Rule::exists('plan_types', 'id')],
            'starts_after_plan_point_id' => [
                'nullable',
                'integer',
                Rule::exists('plan_points', 'id')->where('plan_id', (int) $this->input('plan_id')),
            ],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->has('effective_date')) {
                return;
            }

            $monthlyPlan = $this->route('monthlyPlan');
            if (! $monthlyPlan instanceof MonthlyPlan) {
                return;
            }

            try {
                $effectiveDate = CarbonImmutable::createFromFormat(
                    'Y-m-d',
                    (string) $this->input('effective_date'),
                )->startOfDay();
            } catch (Throwable) {
                return;
            }

            $periodStart = $this->monthlyPlanDate($monthlyPlan->start_date)
                ?? CarbonImmutable::create((int) $monthlyPlan->year, (int) $monthlyPlan->month, 1)->startOfDay();
            $periodEnd = $this->monthlyPlanDate($monthlyPlan->end_date)
                ?? $periodStart->endOfMonth()->startOfDay();
            $studentMonthlyPlan = $this->route('studentMonthlyPlan');
            $studentEffectiveStart = $studentMonthlyPlan instanceof StudentMonthlyPlan
                ? $this->monthlyPlanDate($studentMonthlyPlan->effective_start_date)
                : null;

            if (
                $effectiveDate->lt($periodStart)
                || $effectiveDate->gt($periodEnd)
                || ($studentEffectiveStart !== null && $effectiveDate->lt($studentEffectiveStart))
            ) {
                $validator->errors()->add('effective_date', __('monthly_plans.date_must_be_within_plan_period', [
                    'attribute' => __('monthly_plans.plan_change_date'),
                ]));
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'effective_date' => __('monthly_plans.plan_change_date'),
            'plan_id' => __('monthly_plans.new_plan'),
            'starts_after_plan_point_id' => __('monthly_plans.start_after_plan_point'),
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
