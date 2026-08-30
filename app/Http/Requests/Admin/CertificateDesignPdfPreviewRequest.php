<?php

namespace App\Http\Requests\Admin;

use App\Services\Admin\AdminDataScopeService;
use App\Services\System\CertificateAchievementService;
use App\Services\System\CertificateContentTemplateService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class CertificateDesignPdfPreviewRequest extends FormRequest
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
            'center_id' => [
                'required',
                'integer',
                Rule::exists('centers', 'id')
                    ->where(fn ($query) => app(AdminDataScopeService::class)->applyCenterAccess($query, 'centers')),
            ],
            'plan_point_id' => [
                'required',
                'integer',
                Rule::exists('plan_points', 'id')->where('requires_certificate', true),
            ],
            'design' => [
                'required',
                'array:theme,font,heading_color,student_name_color,content_color,accent_color',
            ],
            'design.theme' => ['required', 'string', Rule::in(array_keys((array) config('certificates.themes', [])))],
            'design.font' => ['required', 'string', Rule::in(array_keys((array) config('certificates.fonts', [])))],
            'design.heading_color' => ['required', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'design.student_name_color' => ['required', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'design.content_color' => ['required', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'design.accent_color' => ['required', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'content_template_id' => [
                'nullable',
                'integer',
                Rule::exists('certificate_content_templates', 'id')
                    ->whereNull('deleted_at')
                    ->where('is_active', true),
            ],
            'content_template_sections' => [
                'nullable',
                'array:'.implode(',', CertificateContentTemplateService::SECTION_KEYS),
            ],
            ...collect(CertificateContentTemplateService::SECTION_KEYS)
                ->mapWithKeys(static fn (string $key): array => [
                    "content_template_sections.{$key}" => [
                        'required_with:content_template_sections',
                        'string',
                        'max:'.CertificateContentTemplateService::SECTION_MAX_LENGTHS[$key],
                    ],
                ])
                ->all(),
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            foreach (array_diff(array_keys($this->all()), [
                'center_id',
                'plan_point_id',
                'design',
                'content_template_id',
                'content_template_sections',
            ]) as $key) {
                $validator->errors()->add(
                    (string) $key,
                    __('validation.prohibited', ['attribute' => (string) $key]),
                );
            }

            if ($validator->errors()->has('plan_point_id')) {
                return;
            }

            $achievement = app(CertificateAchievementService::class)
                ->findPreviewAchievement((int) $this->input('plan_point_id'));

            if ($achievement === null) {
                $validator->errors()->add(
                    'plan_point_id',
                    __('certificates.missing_achievement_data'),
                );
            }

            if (is_array($this->input('content_template_sections'))) {
                foreach (app(CertificateContentTemplateService::class)->sectionValidationErrors(
                    $this->input('content_template_sections'),
                    'content_template_sections',
                ) as $key => $messages) {
                    foreach ($messages as $message) {
                        $validator->errors()->add($key, $message);
                    }
                }
            }
        });
    }
}
