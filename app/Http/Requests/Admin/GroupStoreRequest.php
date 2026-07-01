<?php

namespace App\Http\Requests\Admin;

use App\Services\Admin\AdminDataScopeService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GroupStoreRequest extends FormRequest
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
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('groups', 'name')->where(
                    'center_id',
                    (int) $this->input('center_id'),
                ),
            ],
            'center_id' => [
                'required',
                Rule::exists('centers', 'id')
                    ->where(fn ($query) => $dataScope->applyCenterAccess($query, 'centers')),
            ],
        ];
    }
}
