<?php

namespace App\Http\Requests\Admin;

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
}
