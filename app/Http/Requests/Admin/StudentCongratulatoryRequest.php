<?php

namespace App\Http\Requests\Admin;

use App\Support\PhoneNumberHelper;
use Illuminate\Foundation\Http\FormRequest;

class StudentCongratulatoryRequest extends FormRequest
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
            'reason' => ['required', 'string', 'min:1', 'max:500'],
            'parent_phone_number' => ['nullable', 'string', 'max:20', 'regex:/^\+?[0-9]{8,15}$/', 'required_without:phone_number'],
            'phone_number' => ['nullable', 'string', 'max:20', 'regex:/^\+?[0-9]{8,15}$/', 'required_without:parent_phone_number'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'parent_phone_number' => PhoneNumberHelper::normalizeForStorage($this->input('parent_phone_number')),
            'phone_number' => PhoneNumberHelper::normalizeForStorage($this->input('phone_number')),
        ]);
    }
}
