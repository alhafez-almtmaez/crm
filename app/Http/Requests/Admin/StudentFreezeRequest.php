<?php

namespace App\Http\Requests\Admin;

use App\Support\PhoneNumberHelper;
use Illuminate\Foundation\Http\FormRequest;

class StudentFreezeRequest extends FormRequest
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
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
            'reason' => ['required', 'string', 'min:1', 'max:255'],
            'phone' => ['required', 'string', 'max:20', 'regex:/^\+?[0-9]{8,15}$/'],
            'parent_phone_number' => ['nullable', 'string', 'max:20', 'regex:/^\+?[0-9]{8,15}$/', 'required_without:phone_number'],
            'phone_number' => ['nullable', 'string', 'max:20', 'regex:/^\+?[0-9]{8,15}$/', 'required_without:parent_phone_number'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'phone' => $this->emptyToNull($this->input('phone')),
            'parent_phone_number' => PhoneNumberHelper::normalizeForStorage($this->input('parent_phone_number')),
            'phone_number' => PhoneNumberHelper::normalizeForStorage($this->input('phone_number')),
        ]);
    }

    private function emptyToNull(mixed $value): mixed
    {
        if (!is_string($value)) {
            return $value;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
