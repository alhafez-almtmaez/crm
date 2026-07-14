<?php

namespace App\Http\Requests\Admin;

use App\Services\Admin\AdminDataScopeService;
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
            'center_id' => [
                'nullable',
                'integer',
                Rule::exists('centers', 'id')
                    ->where(fn ($query) => app(AdminDataScopeService::class)->applyCenterAccess($query, 'centers')),
            ],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'alert_status' => ['nullable', Rule::in(['sent', 'pending'])],
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
