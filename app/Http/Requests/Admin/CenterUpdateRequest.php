<?php

namespace App\Http\Requests\Admin;

use App\Models\Center;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CenterUpdateRequest extends FormRequest
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
        /** @var Center $center */
        $center = $this->route('center');

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('centers', 'name')->ignore($center->id),
            ],
            'phone' => ['required', 'string', 'max:20', 'regex:/^\+?[0-9]{8,15}$/'],
            'group_serialized' => ['nullable', 'string', 'max:255'],
            'working_days' => ['required', 'array', 'min:1'],
            'working_days.*' => [
                'string',
                Rule::in(['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday']),
            ],
        ];
    }
}
