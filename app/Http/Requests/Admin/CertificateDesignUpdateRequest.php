<?php

namespace App\Http\Requests\Admin;

use App\Models\Certificate;
use App\Services\Admin\AdminDataScopeService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class CertificateDesignUpdateRequest extends FormRequest
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
        $themeKeys = array_keys((array) config('certificates.themes', []));
        $fontKeys = array_keys((array) config('certificates.fonts', []));
        $achievementTypes = [
            Certificate::ACHIEVEMENT_SURAH,
            Certificate::ACHIEVEMENT_PART,
            Certificate::ACHIEVEMENT_THREE_PARTS,
        ];
        $rules = [
            'center_id' => [
                'required',
                'integer',
                Rule::exists('centers', 'id')
                    ->where(fn ($query) => app(AdminDataScopeService::class)->applyCenterAccess($query, 'centers')),
            ],
            'designs' => ['required', 'array:'.implode(',', $achievementTypes)],
        ];

        foreach ($achievementTypes as $achievementType) {
            $path = "designs.{$achievementType}";
            $rules[$path] = [
                'required',
                'array:theme,font,heading_color,student_name_color,content_color,accent_color',
            ];
            $rules["{$path}.theme"] = ['required', 'string', Rule::in($themeKeys)];
            $rules["{$path}.font"] = ['required', 'string', Rule::in($fontKeys)];

            foreach (['heading_color', 'student_name_color', 'content_color', 'accent_color'] as $color) {
                $rules["{$path}.{$color}"] = ['required', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'];
            }
        }

        return $rules;
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            foreach (array_diff(array_keys($this->all()), ['center_id', 'designs']) as $key) {
                $validator->errors()->add(
                    (string) $key,
                    __('validation.prohibited', ['attribute' => (string) $key]),
                );
            }
        });
    }
}
