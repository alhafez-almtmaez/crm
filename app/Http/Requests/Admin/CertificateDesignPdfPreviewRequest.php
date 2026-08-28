<?php

namespace App\Http\Requests\Admin;

use App\Services\System\CertificateAchievementService;
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
            'center_id' => ['required', 'integer', Rule::exists('centers', 'id')],
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
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            foreach (array_diff(array_keys($this->all()), ['center_id', 'plan_point_id', 'design']) as $key) {
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
        });
    }
}
