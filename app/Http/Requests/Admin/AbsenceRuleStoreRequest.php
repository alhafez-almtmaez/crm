<?php

namespace App\Http\Requests\Admin;

use App\Models\AbsenceRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AbsenceRuleStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $centerId = $this->input('center_id');

        $nullableIntFields = [
            'message_template_id',
            'freeze_working_days_count',
            'deduction_points_count',
        ];

        $payload = [
            'center_id' => ($centerId === '' || $centerId === null) ? null : (int) $centerId,
            'occurrence_number' => (int) $this->input('occurrence_number'),
            'is_active' => $this->boolean('is_active', true),
            'send_to_center_group' => $this->boolean('send_to_center_group', false),
            'meta' => is_array($this->input('meta')) ? $this->input('meta') : null,
            'freeze_reason' => $this->input('freeze_reason') !== '' ? $this->input('freeze_reason') : null,
        ];

        foreach ($nullableIntFields as $field) {
            $value = $this->input($field);
            $payload[$field] = ($value === '' || $value === null) ? null : (int) $value;
        }

        $this->merge($payload);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $centerId = $this->input('center_id');
        $action = (string) $this->input('action');

        return [
            'center_id' => ['nullable', Rule::exists('centers', 'id')],
            'attendance_type' => ['required', Rule::in([
                AbsenceRule::ATTENDANCE_TYPE_ABSENCE,
                AbsenceRule::ATTENDANCE_TYPE_EXCUSED_ABSENCE,
            ])],
            'occurrence_number' => [
                'required',
                'integer',
                'min:1',
                'max:999',
                Rule::unique('absence_rules', 'occurrence_number')
                    ->where(function ($query) use ($centerId): void {
                        $query->where('attendance_type', $this->input('attendance_type'));

                        if ($centerId === null) {
                            $query->whereNull('center_id');

                            return;
                        }

                        $query->where('center_id', (int) $centerId);
                    }),
            ],
            'action' => ['required', Rule::in([
                AbsenceRule::ACTION_FREEZE_STUDENT,
                AbsenceRule::ACTION_DISMISS_STUDENT,
            ])],
            'message_template_id' => [
                'required',
                Rule::exists('message_templates', 'id')->where(
                    fn ($query) => $query->where('is_active', true),
                ),
            ],
            'send_to_center_group' => ['required', 'boolean'],
            'freeze_reason' => ['nullable', 'string', 'max:255'],
            'freeze_working_days_count' => [
                Rule::requiredIf($action === AbsenceRule::ACTION_FREEZE_STUDENT),
                'nullable',
                'integer',
                'min:1',
                'max:30',
            ],
            'deduction_points_count' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'meta' => ['nullable', 'array'],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
