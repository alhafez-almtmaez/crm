<?php

namespace App\Http\Requests\Admin;

use App\Models\Center;
use App\Models\Certificate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
        $genders = [Center::STUDENT_GENDER_MALE, Center::STUDENT_GENDER_FEMALE];
        $achievementTypes = [
            Certificate::ACHIEVEMENT_SURAH,
            Certificate::ACHIEVEMENT_PART,
            Certificate::ACHIEVEMENT_THREE_PARTS,
        ];
        $rules = [
            'designs' => ['required', 'array:'.implode(',', $genders)],
        ];

        foreach ($genders as $gender) {
            $rules["designs.{$gender}"] = ['required', 'array:'.implode(',', $achievementTypes)];

            foreach ($achievementTypes as $achievementType) {
                $path = "designs.{$gender}.{$achievementType}";
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
        }

        return $rules;
    }
}
