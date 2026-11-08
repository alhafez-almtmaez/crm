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
        $canFilterByAdmin = app(AdminDataScopeService::class)->isAdmin($this->user());

        return [
            'search' => ['nullable', 'string', 'max:120'],
            'center_id' => ['nullable', 'integer', 'min:1'],
            'group_id' => ['nullable', 'integer', 'min:1'],
            'admin_id' => $canFilterByAdmin ? ['nullable', 'integer', 'min:1'] : ['prohibited'],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', Rule::when($this->filled('date_from'), 'after_or_equal:date_from')],
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
