<?php

namespace App\Http\Requests\Admin;

use App\Models\Student;
use App\Services\Admin\AdminDataScopeService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StudentIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $dataScope = app(AdminDataScopeService::class);

        return [
            'search' => ['nullable', 'string', 'max:120'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:5', 'max:100'],
            'center_id' => [
                'nullable',
                'integer',
                Rule::exists('centers', 'id')
                    ->where(fn ($query) => $dataScope->applyCenterAccess($query, 'centers')),
            ],
            'group_id' => [
                'nullable',
                'integer',
                Rule::exists('groups', 'id')
                    ->where(fn ($query) => $dataScope->applyGroupAccess($query, 'groups')),
            ],
            'plan_type_id' => ['nullable', 'integer', Rule::exists('plan_types', 'id')],
            'admin_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'is_active' => ['nullable', 'integer', Rule::in([
                Student::STATUS_INACTIVE,
                Student::STATUS_ACTIVE,
                Student::STATUS_FROZEN,
            ])],
            'sort_by' => ['nullable', Rule::in([
                'id',
                'full_name',
                'center_name',
                'group_name',
                'plan_name',
                'admin_name',
                'parent_phone_number',
                'phone_number',
                'is_active',
                'created_at',
            ])],
            'sort_dir' => ['nullable', Rule::in(['asc', 'desc'])],
        ];
    }
}
