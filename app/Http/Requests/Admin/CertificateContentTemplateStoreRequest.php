<?php

namespace App\Http\Requests\Admin;

use App\Services\System\CertificateContentTemplateService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class CertificateContentTemplateStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->exists('is_active')) {
            $this->merge(['is_active' => $this->boolean('is_active')]);
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:150'],
            'sections' => ['required', 'array:'.implode(',', CertificateContentTemplateService::SECTION_KEYS)],
            'is_active' => ['sometimes', 'boolean'],
        ];

        foreach (CertificateContentTemplateService::SECTION_KEYS as $key) {
            $rules["sections.{$key}"] = [
                'required',
                'string',
                'max:'.CertificateContentTemplateService::SECTION_MAX_LENGTHS[$key],
            ];
        }

        return $rules;
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            foreach (array_diff(array_keys($this->all()), ['name', 'sections', 'is_active']) as $key) {
                $validator->errors()->add(
                    (string) $key,
                    __('validation.prohibited', ['attribute' => (string) $key]),
                );
            }

            foreach (app(CertificateContentTemplateService::class)->sectionValidationErrors(
                $this->input('sections'),
            ) as $key => $messages) {
                foreach ($messages as $message) {
                    $validator->errors()->add($key, $message);
                }
            }
        });
    }
}
