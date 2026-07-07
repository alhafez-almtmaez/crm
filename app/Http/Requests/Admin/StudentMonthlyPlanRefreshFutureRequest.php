<?php

namespace App\Http\Requests\Admin;

use App\Models\MonthlyPlan;
use Carbon\CarbonImmutable;
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
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'from_date' => ['required', 'date_format:Y-m-d'],
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

            if ((int) $fromDate->month !== (int) $monthlyPlan->month || (int) $fromDate->year !== (int) $monthlyPlan->year) {
                $validator->errors()->add('from_date', __('validation.date', ['attribute' => 'from_date']));
            }
        });
    }
}
