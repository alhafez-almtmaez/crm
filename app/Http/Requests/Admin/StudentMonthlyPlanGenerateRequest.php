<?php

namespace App\Http\Requests\Admin;

use App\Services\Admin\AdminDataScopeService;
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
            'center_id' => (int) $this->input('center_id'),
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
        ];
    }
}
