<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StudentMonthlyPlanIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'center_id' => $this->filled('center_id') ? (int) $this->input('center_id') : null,
            'group_id' => $this->filled('group_id') ? (int) $this->input('group_id') : null,
            'month' => (int) $this->input('month', now()->month),
            'year' => (int) $this->input('year', now()->year),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $groupRule = Rule::exists('groups', 'id');
        if ($this->filled('center_id')) {
            $groupRule->where('center_id', (int) $this->input('center_id'));
        }

        return [
            'center_id' => ['nullable', Rule::exists('centers', 'id')],
            'group_id' => [
                'nullable',
                $groupRule,
            ],
            'month' => ['required', 'integer', 'min:1', 'max:12'],
            'year' => ['required', 'integer', 'min:2020', 'max:2100'],
        ];
    }
}
