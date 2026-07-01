<?php

namespace App\Imports;

use App\Models\Student;
use App\Services\Admin\AdminDataScopeService;
use App\Support\PhoneNumberHelper;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Concerns\OnEachRow;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Row;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use Throwable;

class StudentsImport implements OnEachRow, SkipsEmptyRows, WithHeadingRow
{
    private int $updated = 0;

    private int $skipped = 0;

    /** @var array<int, string> */
    private array $errors = [];

    /** @var array<int, array{line: int, student: string, reason: string}> */
    private array $skippedRows = [];

    public function __construct(
        private readonly ?int $currentUserId,
        private readonly bool $canAssignAdmin,
        private readonly AdminDataScopeService $dataScope,
    ) {}

    public function onRow(Row $row): void
    {
        /** @var array<string, mixed> $rowData */
        $rowData = $row->toArray();
        $lineNumber = $row->getIndex();
        $studentLabel = $this->studentLabel($rowData);
        $studentId = $this->intOrNull(Arr::get($rowData, 'student_id', Arr::get($rowData, 'id')));
        $hasPlanPointColumn = array_key_exists('plan_point_id', $rowData) || array_key_exists('current_plan_point_id', $rowData);
        $hasPointsBalanceColumn = array_key_exists('points_balance', $rowData);
        $hasMaxDailyWeightColumn = array_key_exists('max_daily_weight', $rowData);

        $student = null;
        if ($studentId !== null) {
            $student = $this->dataScope
                ->applyStudentAccess(Student::query())
                ->find($studentId);

            if ($student === null) {
                $this->markSkipped($lineNumber, $studentLabel, __('validation.exists', ['attribute' => 'student_id']));

                return;
            }
        }

        $payload = [
            'first_name' => $this->nullIfEmpty(Arr::get($rowData, 'first_name')),
            'second_name' => $this->nullIfEmpty(Arr::get($rowData, 'second_name')),
            'middle_name' => $this->nullIfEmpty(Arr::get($rowData, 'middle_name')),
            'last_name' => $this->nullIfEmpty(Arr::get($rowData, 'last_name')),
            'id_number' => $this->nullIfEmpty(Arr::get($rowData, 'id_number')),
            'parent_phone_number' => PhoneNumberHelper::normalizeForStorage(Arr::get($rowData, 'parent_phone_number')),
            'phone_number' => PhoneNumberHelper::normalizeForStorage(Arr::get($rowData, 'phone_number')),
            'email' => $this->nullIfEmpty(Arr::get($rowData, 'email')),
            'date_of_birth' => $this->normalizeDateValue(Arr::get($rowData, 'date_of_birth')),
            'center_id' => $this->intOrNull(Arr::get($rowData, 'center_id')),
            'group_id' => $this->intOrNull(Arr::get($rowData, 'group_id')),
            'plan_type_id' => $this->intOrNull(Arr::get($rowData, 'plan_type_id')),
            'admin_id' => $this->intOrNull(Arr::get($rowData, 'admin_id')),
            'is_active' => $this->intOrNull(Arr::get($rowData, 'is_active')),
        ];

        if ($hasPlanPointColumn) {
            $payload['plan_point_id'] = $this->intOrNull(Arr::get($rowData, 'plan_point_id', Arr::get($rowData, 'current_plan_point_id')));
        }

        if ($hasPointsBalanceColumn) {
            $payload['points_balance'] = $this->intOrNull(Arr::get($rowData, 'points_balance'));
        }

        if ($hasMaxDailyWeightColumn) {
            $payload['max_daily_weight'] = $this->nullIfEmpty(Arr::get($rowData, 'max_daily_weight'));
        }

        $rules = [
            'first_name' => ['required', 'string', 'max:100'],
            'second_name' => ['required', 'string', 'max:100'],
            'middle_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'id_number' => ['nullable', 'string', 'max:30'],
            'parent_phone_number' => ['nullable', 'string', 'max:20', 'regex:/^\+?[0-9]{8,15}$/'],
            'phone_number' => [
                'nullable',
                'string',
                'max:20',
                'regex:/^\+?[0-9]{8,15}$/',
                $student
                    ? Rule::unique('students', 'phone_number')->ignore($student->id)
                    : Rule::unique('students', 'phone_number'),
            ],
            'email' => [
                'nullable',
                'string',
                'email',
                'max:255',
                $student
                    ? Rule::unique('students', 'email')->ignore($student->id)
                    : Rule::unique('students', 'email'),
            ],
            'date_of_birth' => ['nullable', 'date', 'before_or_equal:today'],
            'center_id' => [
                'required',
                Rule::exists('centers', 'id')
                    ->where(fn ($query) => $this->dataScope->applyCenterAccess($query, 'centers')),
            ],
            'group_id' => ['nullable', Rule::exists('groups', 'id')
                ->where(function ($query) use ($payload): void {
                    $query->where('center_id', (int) ($payload['center_id'] ?? 0));
                    $this->dataScope->applyGroupAccess($query, 'groups');
                })],
            'plan_type_id' => ['required', Rule::exists('plan_types', 'id')],
            'admin_id' => ['nullable', Rule::exists('users', 'id')],
            'is_active' => ['nullable', 'integer', Rule::in([0, 1, 2])],
        ];

        if ($hasPlanPointColumn) {
            $rules['plan_point_id'] = [
                'nullable',
                Rule::exists('plan_points', 'id')
                    ->where('plan_id', (int) ($payload['plan_type_id'] ?? 0)),
            ];
        }

        if ($hasPointsBalanceColumn) {
            $rules['points_balance'] = ['nullable', 'integer'];
        }

        if ($hasMaxDailyWeightColumn) {
            $rules['max_daily_weight'] = ['nullable', 'integer', 'min:1', 'max:99'];
        }

        $validator = Validator::make($payload, $rules);

        if ($validator->fails()) {
            $this->markSkipped($lineNumber, $studentLabel, (string) $validator->errors()->first());

            return;
        }

        $validated = $validator->validated();

        $payload = [
            'first_name' => (string) $validated['first_name'],
            'second_name' => (string) $validated['second_name'],
            'middle_name' => (string) $validated['middle_name'],
            'last_name' => (string) $validated['last_name'],
            'full_name' => trim(implode(' ', [
                (string) $validated['first_name'],
                (string) $validated['second_name'],
                (string) $validated['middle_name'],
                (string) $validated['last_name'],
            ])),
            'id_number' => $validated['id_number'] ?? null,
            'parent_phone_number' => PhoneNumberHelper::normalizeForStorage($validated['parent_phone_number'] ?? null),
            'phone_number' => PhoneNumberHelper::normalizeForStorage($validated['phone_number'] ?? null),
            'email' => $validated['email'] ?? null,
            'date_of_birth' => $validated['date_of_birth'] ?? null,
            'center_id' => (int) $validated['center_id'],
            'group_id' => isset($validated['group_id']) ? (int) $validated['group_id'] : null,
            'plan_type_id' => (int) $validated['plan_type_id'],
            'admin_id' => $this->resolveAdminId(
                importedAdminId: $validated['admin_id'] ?? null,
                currentStudentAdminId: $student?->admin_id,
            ),
            'is_active' => (int) ($validated['is_active'] ?? 1),
        ];

        if ($hasPlanPointColumn) {
            $payload['current_plan_point_id'] = isset($validated['plan_point_id'])
                ? (int) $validated['plan_point_id']
                : null;
        }

        if ($hasPointsBalanceColumn) {
            $payload['points_balance'] = (int) ($validated['points_balance'] ?? 0);
        }

        if ($hasMaxDailyWeightColumn) {
            $payload['max_daily_weight'] = (int) ($validated['max_daily_weight'] ?? 2);
        }

        if ($student) {
            $student->update($payload);
        } else {
            Student::query()->create($payload);
        }

        $this->updated++;
    }

    /**
     * @return array{updated: int, skipped: int, errors: array<int, string>, skipped_rows: array<int, array{line: int, student: string, reason: string}>}
     */
    public function result(): array
    {
        return [
            'updated' => $this->updated,
            'skipped' => $this->skipped,
            'errors' => $this->errors,
            'skipped_rows' => $this->skippedRows,
        ];
    }

    private function resolveAdminId(mixed $importedAdminId, ?int $currentStudentAdminId): ?int
    {
        if ($this->canAssignAdmin && $importedAdminId !== null) {
            return (int) $importedAdminId;
        }

        return $currentStudentAdminId ?? $this->currentUserId;
    }

    private function markSkipped(int $lineNumber, string $studentLabel, string $reason): void
    {
        $this->skipped++;
        $message = "Line {$lineNumber}: {$reason}";
        $this->errors[] = $message;
        $this->skippedRows[] = [
            'line' => $lineNumber,
            'student' => $studentLabel,
            'reason' => $reason,
        ];
    }

    /**
     * @param  array<string, mixed>  $rowData
     */
    private function studentLabel(array $rowData): string
    {
        $name = trim(implode(' ', array_filter([
            $this->nullIfEmpty(Arr::get($rowData, 'first_name')),
            $this->nullIfEmpty(Arr::get($rowData, 'second_name')),
            $this->nullIfEmpty(Arr::get($rowData, 'middle_name')),
            $this->nullIfEmpty(Arr::get($rowData, 'last_name')),
        ], static fn (?string $value): bool => $value !== null)));

        if ($name !== '') {
            return $name;
        }

        $studentId = $this->nullIfEmpty(Arr::get($rowData, 'student_id', Arr::get($rowData, 'id')));
        if ($studentId !== null) {
            return "ID {$studentId}";
        }

        $phone = PhoneNumberHelper::normalizeForStorage(Arr::get($rowData, 'phone_number'));
        if ($phone !== null) {
            return $phone;
        }

        $parentPhone = PhoneNumberHelper::normalizeForStorage(Arr::get($rowData, 'parent_phone_number'));
        if ($parentPhone !== null) {
            return $parentPhone;
        }

        $email = $this->nullIfEmpty(Arr::get($rowData, 'email'));

        return $email ?? '-';
    }

    private function nullIfEmpty(mixed $value): ?string
    {
        if ($value === null || is_array($value) || is_object($value) || is_bool($value)) {
            return null;
        }

        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }

    private function intOrNull(mixed $value): ?int
    {
        if (! is_string($value) && ! is_int($value) && ! is_float($value)) {
            return null;
        }

        $trimmed = trim((string) $value);
        if ($trimmed === '') {
            return null;
        }

        return (int) $trimmed;
    }

    private function normalizeDateValue(mixed $value): ?string
    {
        $raw = $this->nullIfEmpty($value);
        if ($raw === null) {
            return null;
        }

        if (preg_match('/^\d{5,}$/', $raw) === 1) {
            try {
                return ExcelDate::excelToDateTimeObject((float) $raw)->format('Y-m-d');
            } catch (Throwable) {
            }
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw) === 1) {
            return $raw;
        }

        foreach (['d/m/Y', 'd-m-Y', 'm/d/Y', 'm-d-Y'] as $format) {
            try {
                $parsed = Carbon::createFromFormat($format, $raw);
                if ($parsed !== false) {
                    return $parsed->format('Y-m-d');
                }
            } catch (Throwable) {
            }
        }

        return $raw;
    }
}
