<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EvaluationIndexRequest extends FormRequest
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
                'date',
                'center_name',
                'admin_name',
                'is_send_absence_alerts',
                'created_at',
            ])],
            'sort_dir' => ['nullable', Rule::in(['asc', 'desc'])],
        ];
    }
}
