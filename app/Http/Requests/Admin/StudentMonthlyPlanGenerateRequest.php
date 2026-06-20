<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StudentMonthlyPlanGenerateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'group_id' => (int) $this->input('group_id'),
            'month' => (int) $this->input('month', now()->month),
            'year' => (int) $this->input('year', now()->year),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'group_id' => ['required', Rule::exists('groups', 'id')],
            'month' => ['required', 'integer', 'min:1', 'max:12'],
            'year' => ['required', 'integer', 'min:2020', 'max:2100'],
        ];
    }
}
