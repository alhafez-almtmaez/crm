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
            'weight' => ['required', 'numeric', 'min:0', 'max:99'],
            'is_standalone' => ['boolean'],
            'is_active' => ['boolean'],
        ];
    }
}
