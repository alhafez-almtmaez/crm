<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class PlanWeightRuleStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'weight' => (float) $this->input('weight', 1),
            'is_standalone' => filter_var($this->input('is_standalone', false), FILTER_VALIDATE_BOOLEAN),
            'is_active' => filter_var($this->input('is_active', true), FILTER_VALIDATE_BOOLEAN),
            'priority' => (int) $this->input('priority', 0),
            'min_pages' => $this->filled('min_pages') ? (int) $this->input('min_pages') : null,
            'max_pages' => $this->filled('max_pages') ? (int) $this->input('max_pages') : null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'pattern' => ['nullable', 'string', 'max:255'],
            'keyword' => ['nullable', 'string', 'max:255'],
            'weight' => ['required', 'numeric', 'min:0', 'max:99'],
            'is_standalone' => ['boolean'],
            'min_pages' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'max_pages' => ['nullable', 'integer', 'min:1', 'max:1000', 'gte:min_pages'],
            'priority' => ['required', 'integer', 'min:0', 'max:100000'],
            'is_active' => ['boolean'],
            'classification' => ['nullable', 'string', 'max:255'],
        ];
    }
}
