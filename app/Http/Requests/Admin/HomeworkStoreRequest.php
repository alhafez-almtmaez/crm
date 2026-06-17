<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class HomeworkStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'center_id' => (int) $this->input('center_id'),
            'date' => (string) $this->input('date'),
            'items' => $this->normalizedItems(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'center_id' => ['required', Rule::exists('centers', 'id')],
            'date' => ['required', 'date_format:Y-m-d'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.student_id' => ['required', Rule::exists('students', 'id')],
            'items.*.points' => ['array'],
            'items.*.points.*.plan_point_id' => ['required', Rule::exists('plan_points', 'id')],
            'items.*.points.*.is_done' => ['boolean'],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function normalizedItems(): array
    {
        $items = $this->input('items', []);
        if (! is_array($items)) {
            return [];
        }

        return array_map(static function ($row): array {
            if (! is_array($row)) {
                return [];
            }

            $points = $row['points'] ?? [];
            if (! is_array($points)) {
                $points = [];
            }

            return [
                'student_id' => isset($row['student_id']) ? (int) $row['student_id'] : null,
                'points' => array_map(static fn ($point): array => [
                    'plan_point_id' => isset($point['plan_point_id']) ? (int) $point['plan_point_id'] : null,
                    'is_done' => filter_var($point['is_done'] ?? false, FILTER_VALIDATE_BOOLEAN),
                ], array_filter($points, static fn ($point): bool => is_array($point))),
            ];
        }, $items);
    }
}
