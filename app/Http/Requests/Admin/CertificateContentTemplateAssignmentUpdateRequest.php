<?php

namespace App\Http\Requests\Admin;

use App\Models\CertificateContentTemplateAssignment;
use App\Services\Admin\AdminDataScopeService;
use App\Services\System\CertificateContentTemplateService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class CertificateContentTemplateAssignmentUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'template_id' => [
                'required',
                'integer',
                Rule::exists('certificate_content_templates', 'id')
                    ->whereNull('deleted_at')
                    ->where('is_active', true),
            ],
            'scope_type' => ['required', Rule::in(CertificateContentTemplateAssignment::SCOPES)],
            'center_id' => [
                'nullable',
                'integer',
                Rule::exists('centers', 'id')
                    ->where(fn ($query) => app(AdminDataScopeService::class)->applyCenterAccess($query, 'centers')),
            ],
            'student_gender' => ['nullable', Rule::in(CertificateContentTemplateService::GENDERS)],
            'achievement_type' => [
                'nullable',
                Rule::in([
                    ...CertificateContentTemplateService::ACHIEVEMENT_TYPES,
                    CertificateContentTemplateService::ALL_ACHIEVEMENT_TYPES,
                ]),
            ],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            foreach (array_diff(
                array_keys($this->all()),
                ['template_id', 'scope_type', 'center_id', 'student_gender', 'achievement_type'],
            ) as $key) {
                $validator->errors()->add(
                    (string) $key,
                    __('validation.prohibited', ['attribute' => (string) $key]),
                );
            }

            $scopeType = $this->input('scope_type');
            $centerId = $this->input('center_id');
            $gender = $this->input('student_gender');

            if ($scopeType === CertificateContentTemplateAssignment::SCOPE_CENTER) {
                if (! is_numeric($centerId) || (int) $centerId < 1) {
                    $validator->errors()->add('center_id', __('validation.required', ['attribute' => 'center_id']));
                }
                if ($gender !== null && $gender !== '') {
                    $validator->errors()->add('student_gender', __('validation.prohibited', ['attribute' => 'student_gender']));
                }

                return;
            }

            if ($centerId !== null && $centerId !== '') {
                $validator->errors()->add('center_id', __('validation.prohibited', ['attribute' => 'center_id']));
            }

            if ($scopeType === CertificateContentTemplateAssignment::SCOPE_GENDER) {
                if (! in_array($gender, CertificateContentTemplateService::GENDERS, true)) {
                    $validator->errors()->add('student_gender', __('validation.required', ['attribute' => 'student_gender']));
                }

                return;
            }

            if ($gender !== null && $gender !== '') {
                $validator->errors()->add('student_gender', __('validation.prohibited', ['attribute' => 'student_gender']));
            }
        });
    }
}
