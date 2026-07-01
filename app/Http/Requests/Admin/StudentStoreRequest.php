<?php

namespace App\Http\Requests\Admin;

use App\Services\Admin\AdminDataScopeService;
use App\Support\PhoneNumberHelper;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StudentStoreRequest extends FormRequest
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
        $centerRule = Rule::exists('centers', 'id')
            ->where(fn ($query) => $dataScope->applyCenterAccess($query, 'centers'));
        $groupRule = Rule::exists('groups', 'id')
            ->where(function ($query) use ($dataScope): void {
                $query->where('center_id', (int) $this->input('center_id'));
                $dataScope->applyGroupAccess($query, 'groups');
            });

        return [
            'first_name' => ['required', 'string', 'max:100'],
            'second_name' => ['required', 'string', 'max:100'],
            'middle_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'id_number' => ['nullable', 'string', 'max:30'],
            'parent_phone_number' => [
                'nullable',
                'string',
                'max:20',
                'regex:/^\+?[0-9]{8,15}$/',
                'required_without:phone_number',
            ],
            'phone_number' => [
                'nullable',
                'string',
                'max:20',
                'regex:/^\+?[0-9]{8,15}$/',
                'required_without:parent_phone_number',
                Rule::unique('students', 'phone_number'),
            ],
            'email' => ['nullable', 'string', 'email', 'max:255', Rule::unique('students', 'email')],
            'date_of_birth' => ['nullable', 'date', 'before_or_equal:today'],
            'center_id' => ['required', $centerRule],
            'group_id' => [
                'nullable',
                $groupRule,
            ],
            'plan_type_id' => ['required', Rule::exists('plan_types', 'id')],
            'current_plan_point_id' => [
                'nullable',
                Rule::exists('plan_points', 'id')->where('plan_id', (int) $this->input('plan_type_id')),
            ],
            'max_daily_weight' => ['required', 'integer', 'min:1', 'max:99'],
            'points_balance' => ['nullable', 'integer'],
            'admin_id' => [
                Rule::requiredIf((bool) $this->user()?->hasRole('admin')),
                'nullable',
                Rule::exists('users', 'id'),
            ],
            'is_active' => ['nullable', 'integer', Rule::in([0, 1, 2])],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'id_number' => $this->emptyToNull($this->input('id_number')),
            'parent_phone_number' => PhoneNumberHelper::normalizeForStorage($this->input('parent_phone_number')),
            'phone_number' => PhoneNumberHelper::normalizeForStorage($this->input('phone_number')),
            'email' => $this->emptyToNull($this->input('email')),
            'date_of_birth' => $this->emptyToNull($this->input('date_of_birth')),
            'group_id' => $this->emptyToNull($this->input('group_id')),
            'current_plan_point_id' => $this->emptyToNull($this->input('current_plan_point_id')),
            'admin_id' => $this->emptyToNull($this->input('admin_id')),
            'max_daily_weight' => $this->input('max_daily_weight', 2),
            'points_balance' => $this->input('points_balance', 0),
            'is_active' => $this->input('is_active', 1),
        ]);
    }

    private function emptyToNull(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
