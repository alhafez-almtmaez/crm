<?php

namespace App\Http\Requests\Admin;

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
        return [
            'search' => ['nullable', 'string', 'max:120'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:5', 'max:100'],
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
