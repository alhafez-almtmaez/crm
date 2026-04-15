<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MessageTemplateStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_active' => $this->boolean('is_active', true),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'key' => [
                'required',
                'string',
                'max:120',
                'regex:/^[a-z0-9_.-]+$/',
                Rule::unique('message_templates', 'key'),
            ],
            'name' => ['required', 'string', 'max:150'],
            'locale' => ['nullable', 'string', 'max:8'],
            'content' => ['required', 'string'],
            'placeholders' => ['nullable', 'array'],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
