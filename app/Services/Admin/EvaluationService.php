<?php

namespace App\Services\Admin;

use App\Models\Center;
use App\Models\Evaluation;
use App\Models\EvaluationStudent;
use App\Models\Student;
use App\Models\StudentFreeze;
use App\Services\Admin\AbsenceRules\AbsenceRuleEngine;
use App\Services\System\DateTimeFormatterService;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class EvaluationService
{
    public function __construct(
        private readonly DateTimeFormatterService $dateTimeFormatter,
        private readonly AbsenceRuleEngine $absenceRuleEngine,
    )
    {
    }

    /**
     * @param array<string, mixed> $filters
     */
    public function list(array $filters): LengthAwarePaginator
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $perPage = (int) ($filters['per_page'] ?? 10);
        $sortBy = (string) ($filters['sort_by'] ?? 'id');
        $sortDir = (string) ($filters['sort_dir'] ?? 'desc');
        $sortMap = [
            'id' => 'evaluations.id',
            'date' => 'evaluations.date',
            'center_name' => 'centers.name',
            'admin_name' => 'admins.name',
            'is_send_absence_alerts' => 'evaluations.is_send_absence_alerts',
            'created_at' => 'evaluations.created_at',
        ];

        $sortColumn = $sortMap[$sortBy] ?? 'evaluations.id';
        $sortDir = in_array($sortDir, ['asc', 'desc'], true) ? $sortDir : 'desc';
        $isAdmin = (bool) Auth::user()?->hasRole('admin');
        $currentUserId = Auth::id();

        $rows = Evaluation::query()
            ->leftJoin('centers', 'evaluations.center_id', '=', 'centers.id')
            ->leftJoin('users as admins', 'evaluations.admin_id', '=', 'admins.id')
            ->select([
                'evaluations.id',
                'evaluations.ulid',
                'evaluations.date',
                'evaluations.center_id',
                'evaluations.admin_id',
                'evaluations.is_send_absence_alerts',
                'evaluations.created_at',
                'centers.name as center_name',
                'admins.name as admin_name',
            ])
            ->when(! $isAdmin, function ($query) use ($currentUserId): void {
                $query->where('evaluations.admin_id', $currentUserId);
            })
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($builder) use ($search): void {
                    $builder
                        ->where('evaluations.id', 'like', "%{$search}%")
                        ->orWhere('evaluations.date', 'like', "%{$search}%")
                        ->orWhere('centers.name', 'like', "%{$search}%")
                        ->orWhere('admins.name', 'like', "%{$search}%");
                });
            })
            ->orderBy($sortColumn, $sortDir)
            ->paginate($perPage)
            ->withQueryString();

        $rows->setCollection(
            $rows->getCollection()->map(function ($row) {
                $row->setAttribute('created_at_formatted', $this->dateTimeFormatter->formatForAdmin($row->created_at));
                $row->setAttribute(
                    'date_formatted',
                    Carbon::parse((string) $row->date)->locale(app()->getLocale())->translatedFormat('l ، j F ، Y'),
                );

                return $row;
            }),
        );

        return $rows;
    }

    /**
     * @return array<int, array{id: int, name: string}>
     */
    public function centerOptions(): array
    {
        return Center::query()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(static fn (Center $center): array => [
                'id' => $center->id,
                'name' => $center->name,
            ])
            ->all();
    }

    /**
     * @return array{selected_center_id: ?int, selected_date: string, students: array<int, array<string, mixed>>, existing_evaluation_id: ?int}
     */
    public function createFormPayload(?int $centerId, ?string $date): array
    {
        $resolvedDate = $this->resolveDate($date);
        $resolvedCenterId = $centerId !== null && $centerId > 0 ? $centerId : null;

        if ($resolvedCenterId === null) {
            return [
                'selected_center_id' => null,
                'selected_date' => $resolvedDate,
                'students' => [],
                'existing_evaluation_id' => null,
            ];
        }

        $existingEvaluationId = Evaluation::query()
            ->where('center_id', $resolvedCenterId)
            ->whereDate('date', $resolvedDate)
            ->value('id');

        $students = $existingEvaluationId === null
            ? $this->studentRowsForCenterDate($resolvedCenterId, $resolvedDate)
            : [];

        return [
            'selected_center_id' => $resolvedCenterId,
            'selected_date' => $resolvedDate,
            'students' => $students,
            'existing_evaluation_id' => $existingEvaluationId !== null ? (int) $existingEvaluationId : null,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function editStudentRows(Evaluation $evaluation): array
    {
        $date = Carbon::parse((string) $evaluation->date)->toDateString();

        return $this->studentRowsForCenterDate(
            centerId: (int) $evaluation->center_id,
            date: $date,
            evaluation: $evaluation,
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    public function create(array $data): Evaluation
    {
        $centerId = (int) $data['center_id'];
        $date = $this->resolveDate((string) $data['date']);
        $adminId = Auth::id();

        $exists = Evaluation::query()
            ->where('center_id', $centerId)
            ->whereDate('date', $date)
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'date' => __('evaluations.already_exists_for_center_date'),
            ]);
        }

        return DB::transaction(function () use ($centerId, $date, $adminId, $data): Evaluation {
            $evaluation = Evaluation::query()->create([
                'ulid' => (string) Str::ulid(),
                'date' => $date,
                'center_id' => $centerId,
                'admin_id' => $adminId,
                'is_send_absence_alerts' => false,
            ]);

            $this->replaceEvaluationRows($evaluation, $data['items'] ?? []);
            $this->ensureFrozenRows($evaluation);

            return $evaluation->refresh();
        });
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(Evaluation $evaluation, array $data): Evaluation
    {
        return DB::transaction(function () use ($evaluation, $data): Evaluation {
            $this->syncEvaluationRows($evaluation, $data['items'] ?? []);
            $this->ensureFrozenRows($evaluation);

            if ($evaluation->is_send_absence_alerts) {
                $evaluation->update(['is_send_absence_alerts' => false]);
            }

            return $evaluation->refresh();
        });
    }

    public function delete(Evaluation $evaluation): void
    {
        $evaluation->delete();
    }

    /**
     * @return array{
     *     processed: int,
     *     skipped: int,
     *     errors: array<int, string>,
     *     alerts_marked_as_sent: bool
     * }
     */
    public function sendAbsenceAlerts(Evaluation $evaluation): array
    {
        return $this->absenceRuleEngine->processEvaluation(
            evaluationId: $evaluation->id,
            executedBy: Auth::id(),
        );
    }

    /**
     * @param array<int, mixed> $items
     */
    private function replaceEvaluationRows(Evaluation $evaluation, array $items): void
    {
        $rows = [];
        $rowsByStudent = [];
        $now = now();

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $studentId = (int) ($item['student_id'] ?? 0);
            $attendance = (int) ($item['attendances'] ?? EvaluationStudent::ATTENDANCE_PRESENT);

            if ($studentId <= 0) {
                continue;
            }

            if ($attendance === EvaluationStudent::ATTENDANCE_EXEMPT) {
                $rowsByStudent[$studentId] = null;
                continue;
            }

            $isPresent = $attendance === EvaluationStudent::ATTENDANCE_PRESENT;

            $rowsByStudent[$studentId] = [
                'alhifz' => $isPresent ? $this->normalizeScore($item, 'alhifz') : null,
                'warud' => $isPresent ? $this->normalizeScore($item, 'warud') : null,
                'akhlaqi' => $isPresent ? $this->normalizeScore($item, 'akhlaqi') : null,
                'tajwid' => $isPresent ? $this->normalizeScore($item, 'tajwid') : null,
                'note' => $this->normalizeNote($item),
                'attendances' => $attendance,
                'student_id' => $studentId,
                'user_id' => $studentId,
                'evaluation_id' => $evaluation->id,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach ($rowsByStudent as $row) {
            if ($row !== null) {
                $rows[] = $row;
            }
        }

        if ($rows !== []) {
            EvaluationStudent::query()->insert($rows);
        }
    }

    /**
     * @param array<int, mixed> $items
     */
    private function syncEvaluationRows(Evaluation $evaluation, array $items): void
    {
        $existingRows = EvaluationStudent::query()
            ->where('evaluation_id', $evaluation->id)
            ->where('attendances', '!=', EvaluationStudent::ATTENDANCE_FROZEN)
            ->get()
            ->mapWithKeys(static function (EvaluationStudent $row): array {
                $studentId = $row->resolvedStudentId();
                if ($studentId === null) {
                    return [];
                }

                return [(int) $studentId => $row];
            });

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $studentId = (int) ($item['student_id'] ?? 0);
            if ($studentId <= 0) {
                continue;
            }

            $attendance = (int) ($item['attendances'] ?? EvaluationStudent::ATTENDANCE_PRESENT);
            if ($attendance === EvaluationStudent::ATTENDANCE_EXEMPT) {
                continue;
            }

            $isPresent = $attendance === EvaluationStudent::ATTENDANCE_PRESENT;
            $payload = [
                'alhifz' => $isPresent ? $this->normalizeScore($item, 'alhifz') : null,
                'warud' => $isPresent ? $this->normalizeScore($item, 'warud') : null,
                'akhlaqi' => $isPresent ? $this->normalizeScore($item, 'akhlaqi') : null,
                'tajwid' => $isPresent ? $this->normalizeScore($item, 'tajwid') : null,
                'note' => $this->normalizeNote($item),
                'attendances' => $attendance,
                'student_id' => $studentId,
                'user_id' => $studentId,
                'evaluation_id' => $evaluation->id,
            ];

            /** @var EvaluationStudent|null $existing */
            $existing = $existingRows->get($studentId);
            if ($existing !== null) {
                $existing->fill($payload);
                if ($existing->isDirty()) {
                    $existing->save();
                }
                continue;
            }

            EvaluationStudent::query()->create($payload);
        }
    }

    private function ensureFrozenRows(Evaluation $evaluation): void
    {
        if ($evaluation->center_id === null || $evaluation->date === null) {
            return;
        }

        $date = Carbon::parse((string) $evaluation->date)->toDateString();
        $frozenStudentIds = $this->frozenStudentIdsForDate((int) $evaluation->center_id, $date);
        if ($frozenStudentIds === []) {
            return;
        }

        $existingStudentIds = EvaluationStudent::query()
            ->where('evaluation_id', $evaluation->id)
            ->whereIn('student_id', $frozenStudentIds)
            ->pluck('student_id')
            ->filter(static fn ($value): bool => $value !== null)
            ->map(static fn ($value): int => (int) $value)
            ->all();

        $existingLookup = array_fill_keys($existingStudentIds, true);
        $now = now();
        $rows = [];

        foreach ($frozenStudentIds as $studentId) {
            if (isset($existingLookup[$studentId])) {
                continue;
            }

            $rows[] = [
                'alhifz' => null,
                'warud' => null,
                'akhlaqi' => null,
                'tajwid' => null,
                'note' => null,
                'attendances' => EvaluationStudent::ATTENDANCE_FROZEN,
                'student_id' => $studentId,
                'user_id' => $studentId,
                'evaluation_id' => $evaluation->id,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if ($rows !== []) {
            EvaluationStudent::query()->insert($rows);
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function studentRowsForCenterDate(int $centerId, string $date, ?Evaluation $evaluation = null): array
    {
        $existingItemsByStudent = collect();
        if ($evaluation !== null) {
            $existingItemsByStudent = $evaluation->evaluationStudents()
                ->where('attendances', '!=', EvaluationStudent::ATTENDANCE_FROZEN)
                ->get()
                ->mapWithKeys(static function (EvaluationStudent $item): array {
                    $studentId = $item->resolvedStudentId();
                    if ($studentId === null) {
                        return [];
                    }

                    return [(int) $studentId => $item];
                });
        }

        $frozenLookup = array_fill_keys($this->frozenStudentIdsForDate($centerId, $date), true);

        $baseStudents = Student::query()
            ->leftJoin('plan_types', 'students.plan_type_id', '=', 'plan_types.id')
            ->leftJoin('groups', 'students.group_id', '=', 'groups.id')
            ->where('students.center_id', $centerId)
            ->where('students.is_active', Student::STATUS_ACTIVE)
            ->orderBy('students.plan_type_id')
            ->orderBy('students.full_name')
            ->select([
                'students.id',
                'students.full_name',
                'plan_types.name as plan_name',
                'groups.name as group_name',
            ])
            ->get();

        $rows = [];
        $included = [];

        foreach ($baseStudents as $student) {
            $studentId = (int) $student->id;
            if (isset($frozenLookup[$studentId]) && !$existingItemsByStudent->has($studentId)) {
                continue;
            }

            $rows[] = $this->studentRowData(
                studentId: $studentId,
                fullName: (string) $student->full_name,
                planName: $student->plan_name !== null ? (string) $student->plan_name : null,
                groupName: $student->group_name !== null ? (string) $student->group_name : null,
                item: $existingItemsByStudent->get($studentId),
            );
            $included[$studentId] = true;
        }

        foreach ($existingItemsByStudent as $studentId => $item) {
            $studentId = (int) $studentId;
            if (isset($included[$studentId])) {
                continue;
            }

            $student = Student::query()
                ->leftJoin('plan_types', 'students.plan_type_id', '=', 'plan_types.id')
                ->leftJoin('groups', 'students.group_id', '=', 'groups.id')
                ->where('students.id', $studentId)
                ->select([
                    'students.id',
                    'students.full_name',
                    'plan_types.name as plan_name',
                    'groups.name as group_name',
                ])
                ->first();

            if ($student === null) {
                // Legacy/orphaned evaluation row (student deleted): skip to keep edit form valid.
                continue;
            }

            $rows[] = $this->studentRowData(
                studentId: $studentId,
                fullName: (string) ($student->full_name ?? ''),
                planName: $student?->plan_name !== null ? (string) $student->plan_name : null,
                groupName: $student?->group_name !== null ? (string) $student->group_name : null,
                item: $item,
            );
        }

        return $rows;
    }

    private function studentRowData(
        int $studentId,
        string $fullName,
        ?string $planName,
        ?string $groupName,
        ?EvaluationStudent $item = null,
    ): array {
        return [
            'student_id' => $studentId,
            'full_name' => $fullName,
            'plan_name' => $planName,
            'group_name' => $groupName,
            'alhifz' => $item?->alhifz ?? 10,
            'warud' => $item?->warud ?? 10,
            'akhlaqi' => $item?->akhlaqi ?? 10,
            'tajwid' => $item?->tajwid ?? 10,
            'note' => $item?->note,
            'attendances' => $item?->attendances ?? EvaluationStudent::ATTENDANCE_PRESENT,
            'was_edited' => $item !== null
                && $item->created_at !== null
                && $item->updated_at !== null
                && $item->updated_at->gt($item->created_at),
        ];
    }

    /**
     * @return array<int, int>
     */
    private function frozenStudentIdsForDate(int $centerId, string $date): array
    {
        return StudentFreeze::query()
            ->join('students', 'student_freezes.student_id', '=', 'students.id')
            ->where('students.center_id', $centerId)
            ->whereDate('student_freezes.from', '<=', $date)
            ->whereDate('student_freezes.to', '>=', $date)
            ->where(function ($query) use ($date): void {
                $query->where('student_freezes.is_active', true)
                    ->orWhere(function ($inner) use ($date): void {
                        $inner->whereNotNull('student_freezes.unfrozen_at')
                            ->whereDate('student_freezes.unfrozen_at', '>', $date);
                    });
            })
            ->pluck('student_freezes.student_id')
            ->unique()
            ->map(static fn ($id): int => (int) $id)
            ->values()
            ->all();
    }

    /**
     * @param array<string, mixed> $item
     */
    private function normalizeScore(array $item, string $key): int
    {
        $value = Arr::get($item, $key);
        if ($value === null || $value === '') {
            return 10;
        }

        $score = (int) $value;

        return max(0, min(10, $score));
    }

    /**
     * @param array<string, mixed> $item
     */
    private function normalizeNote(array $item): ?string
    {
        $value = Arr::get($item, 'note');
        if (! is_string($value)) {
            return null;
        }

        $note = trim($value);

        return $note !== '' ? $note : null;
    }

    private function resolveDate(?string $date): string
    {
        if ($date === null || trim($date) === '') {
            return now()->toDateString();
        }

        return Carbon::parse($date)->toDateString();
    }
}
