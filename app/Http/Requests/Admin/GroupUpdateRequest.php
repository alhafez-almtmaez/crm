<?php

namespace App\Http\Requests\Admin;

use App\Models\Group;
use App\Services\Admin\AdminDataScopeService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GroupUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        $group = $this->route('group');

        return ! $group instanceof Group || app(AdminDataScopeService::class)->canAccessGroup($group);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Group $group */
        $group = $this->route('group');
        $dataScope = app(AdminDataScopeService::class);

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('groups', 'name')
                    ->where('center_id', (int) $this->input('center_id'))
                    ->ignore($group->id),
            ],
            'center_id' => [
                'required',
                Rule::exists('centers', 'id')
                    ->where(function ($query) use ($dataScope): void {
                        $query->whereNull('archived_at');
                        $dataScope->applyCenterAccess($query, 'centers');
                    }),
            ],
            'group_serialized' => ['nullable', 'string', 'max:255'],
            'working_days' => ['required', 'array', 'min:1'],
            'working_days.*' => [
                'string',
                Rule::in(['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday']),
            ],
        ];
    }
}
