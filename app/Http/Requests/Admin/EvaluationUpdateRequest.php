<?php

namespace App\Http\Requests\Admin;

use App\Models\Evaluation;
use App\Models\EvaluationStudent;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EvaluationUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $routeEvaluation = $this->route('evaluation');
        $fallbackEvaluationType = $routeEvaluation instanceof Evaluation
            ? (int) ($routeEvaluation->evaluation_type ?? Evaluation::TYPE_ALHIFZ)
            : Evaluation::TYPE_ALHIFZ;
        $items = $this->input('items', []);
        if (! is_array($items)) {
            $items = [];
        }

        $normalizedItems = array_map(static function ($row): array {
            if (! is_array($row)) {
                return [];
            }

            $toNullableInt = static function (mixed $value): ?int {
                if ($value === null || $value === '') {
                    return null;
                }

                return (int) $value;
            };

            return [
                'student_id' => isset($row['student_id']) ? (int) $row['student_id'] : null,
                'attendances' => isset($row['attendances']) ? (int) $row['attendances'] : EvaluationStudent::ATTENDANCE_PRESENT,
                'alhifz' => $toNullableInt($row['alhifz'] ?? null),
                'warud' => $toNullableInt($row['warud'] ?? null),
                'akhlaqi' => $toNullableInt($row['akhlaqi'] ?? null),
                'tajwid' => $toNullableInt($row['tajwid'] ?? null),
                'note' => isset($row['note']) ? trim((string) $row['note']) : null,
            ];
        }, $items);

        $this->merge([
            'evaluation_type' => (int) $this->input('evaluation_type', $fallbackEvaluationType),
            'items' => $normalizedItems,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'evaluation_type' => ['required', 'integer', Rule::in([Evaluation::TYPE_ALHIFZ, Evaluation::TYPE_TAJWID])],
            'items' => ['required', 'array', 'min:1'],
            'items.*.student_id' => ['required', Rule::exists('students', 'id')],
            'items.*.attendances' => ['required', Rule::in([
                EvaluationStudent::ATTENDANCE_PRESENT,
                EvaluationStudent::ATTENDANCE_EXCUSED_ABSENCE,
                EvaluationStudent::ATTENDANCE_ABSENCE,
            ])],
            'items.*.alhifz' => ['nullable', 'integer', 'min:0', 'max:10'],
            'items.*.warud' => ['nullable', 'integer', 'min:0', 'max:10'],
            'items.*.akhlaqi' => ['nullable', 'integer', 'min:0', 'max:10'],
            'items.*.tajwid' => ['nullable', 'integer', 'min:0', 'max:10'],
            'items.*.note' => ['nullable', 'string', 'max:255'],
        ];
    }
}
