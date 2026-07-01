<?php

namespace App\Http\Requests\Admin;

use App\Models\Homework;
use App\Services\Admin\AdminDataScopeService;
use Illuminate\Validation\Validator;

class HomeworkUpdateRequest extends HomeworkStoreRequest
{
    public function authorize(): bool
    {
        $homework = $this->route('homework');

        return ! $homework instanceof Homework || app(AdminDataScopeService::class)->canAccessHomework($homework);
    }

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

    protected function rowCenterId(): ?int
    {
        $homework = $this->route('homework');

        return $homework instanceof Homework ? (int) $homework->center_id : null;
    }
}
