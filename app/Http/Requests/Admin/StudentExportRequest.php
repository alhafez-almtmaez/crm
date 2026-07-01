<?php

namespace App\Http\Requests\Admin;

use App\Services\Admin\AdminDataScopeService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StudentExportRequest extends FormRequest
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
            'center_id' => [
                'nullable',
                Rule::exists('centers', 'id')
                    ->where(fn ($query) => $dataScope->applyCenterAccess($query, 'centers')),
            ],
        ];
    }
}
