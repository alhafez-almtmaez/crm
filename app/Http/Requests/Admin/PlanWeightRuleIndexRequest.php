<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class PlanWeightRuleIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'search' => trim((string) $this->input('search', '')),
            'per_page' => (int) $this->input('per_page', 25),
            'sort_by' => (string) $this->input('sort_by', 'priority'),
            'sort_dir' => (string) $this->input('sort_dir', 'desc'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'sort_by' => ['nullable', 'string', 'in:id,name,weight,is_standalone,priority,is_active,created_at'],
            'sort_dir' => ['nullable', 'string', 'in:asc,desc'],
        ];
    }
}
