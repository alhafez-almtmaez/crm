<?php

namespace App\Http\Requests\Admin;

use App\Services\Admin\AdminDataScopeService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class HomeworkIndexRequest extends FormRequest
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
        return [
            'search' => ['nullable', 'string', 'max:120'],
            'center_id' => [
                'nullable',
                'integer',
                Rule::exists('centers', 'id')
                    ->where(fn ($query) => app(AdminDataScopeService::class)->applyCenterAccess($query, 'centers')),
            ],
            'group_id' => [
                'nullable',
                'integer',
                Rule::exists('groups', 'id')
                    ->where(fn ($query) => app(AdminDataScopeService::class)->applyGroupAccess($query, 'groups')),
            ],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:5', 'max:100'],
            'sort_by' => ['nullable', Rule::in([
                'id',
                'date',
                'center_name',
                'group_name',
                'admin_name',
                'created_at',
            ])],
            'sort_dir' => ['nullable', Rule::in(['asc', 'desc'])],
        ];
    }
}
