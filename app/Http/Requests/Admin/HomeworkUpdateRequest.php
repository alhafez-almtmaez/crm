<?php

namespace App\Http\Requests\Admin;

use Illuminate\Validation\Validator;

class HomeworkUpdateRequest extends HomeworkStoreRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = parent::rules();
        unset($rules['center_id'], $rules['date']);

        return $rules;
    }

    public function withValidator(Validator $validator): void
    {
        //
    }
}
