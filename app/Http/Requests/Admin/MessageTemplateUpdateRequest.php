<?php

namespace App\Http\Requests\Admin;

use App\Models\MessageTemplate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MessageTemplateUpdateRequest extends FormRequest
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
        /** @var MessageTemplate $template */
        $template = $this->route('message_template');

        return [
            'key' => [
                'required',
                'string',
                'max:120',
                'regex:/^[a-z0-9_.-]+$/',
                Rule::unique('message_templates', 'key')->ignore($template->id),
            ],
            'name' => ['required', 'string', 'max:150'],
            'locale' => ['nullable', 'string', 'max:8'],
            'content' => ['required', 'string'],
            'placeholders' => ['nullable', 'array'],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
