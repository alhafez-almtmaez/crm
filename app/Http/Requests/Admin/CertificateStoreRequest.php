<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class CertificateStoreRequest extends FormRequest
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
            'plan_point_id' => ['required', 'integer', 'exists:plan_points,id'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            foreach (array_diff(array_keys($this->all()), ['plan_point_id']) as $key) {
                $validator->errors()->add(
                    (string) $key,
                    __('validation.prohibited', ['attribute' => (string) $key]),
                );
            }
        });
    }
}
