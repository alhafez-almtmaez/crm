<?php

namespace App\Http\Requests\Admin;

use App\Models\Center;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CenterStoreRequest extends FormRequest
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
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('centers', 'name'),
            ],
            'certificate_name' => ['nullable', 'string', 'max:255'],
            'student_gender' => ['required', Rule::in([
                Center::STUDENT_GENDER_MALE,
                Center::STUDENT_GENDER_FEMALE,
            ])],
            'phone' => ['required', 'string', 'max:20', 'regex:/^\+?[0-9]{8,15}$/'],
            'group_serialized' => ['nullable', 'string', 'max:255'],
            'working_days' => ['required', 'array', 'min:1'],
            'working_days.*' => [
                'string',
                Rule::in(['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday']),
            ],
            'show_center_manager_signature' => ['required', 'boolean'],
        ];
    }
}
