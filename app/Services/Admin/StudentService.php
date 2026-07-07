<?php

namespace App\Services\Admin;

use App\Imports\StudentsImport;
use App\Models\Center;
use App\Models\Plan;
use App\Models\PlanPoint;
use App\Models\Student;
use App\Models\User;
use App\Services\System\DateTimeFormatterService;
use App\Support\DailyWeightLimits;
use App\Support\PhoneNumberHelper;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Facades\Excel;

class StudentService
{
    public function __construct(
        private readonly DateTimeFormatterService $dateTimeFormatter,
        private readonly AdminDataScopeService $dataScope,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     */
    public function list(array $filters): LengthAwarePaginator
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $perPage = (int) ($filters['per_page'] ?? 10);
        $sortBy = (string) ($filters['sort_by'] ?? 'id');
        $sortDir = (string) ($filters['sort_dir'] ?? 'desc');
        $sortMap = [
            'id' => 'students.id',
            'full_name' => 'students.full_name',
            'center_name' => 'centers.name',
            'group_name' => 'groups.name',
            'plan_name' => 'plan_types.name',
            'admin_name' => 'admins.name',
            'parent_phone_number' => 'students.parent_phone_number',
            'phone_number' => 'students.phone_number',
            'is_active' => 'students.is_active',
            'created_at' => 'students.created_at',
        ];

        $sortColumn = $sortMap[$sortBy] ?? 'students.id';
        $sortDir = in_array($sortDir, ['asc', 'desc'], true) ? $sortDir : 'desc';

        $query = Student::query()
            ->leftJoin('centers', 'students.center_id', '=', 'centers.id')
            ->leftJoin('groups', 'students.group_id', '=', 'groups.id')
            ->leftJoin('plan_types', 'students.plan_type_id', '=', 'plan_types.id')
            ->leftJoin('users as admins', 'students.admin_id', '=', 'admins.id')
            ->select([
                'students.id',
                'students.full_name',
                'students.parent_phone_number',
                'students.phone_number',
                'students.is_active',
                'students.created_at',
                'centers.name as center_name',
                'groups.name as group_name',
                'plan_types.name as plan_name',
                'admins.name as admin_name',
            ])
            ->tap(fn ($query) => $this->dataScope->applyStudentAccess($query, 'students'))
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($builder) use ($search): void {
                    $builder
                        ->where('students.full_name', 'like', "%{$search}%")
                        ->orWhere('students.parent_phone_number', 'like', "%{$search}%")
                        ->orWhere('students.phone_number', 'like', "%{$search}%")
                        ->orWhere('students.email', 'like', "%{$search}%")
                        ->orWhere('students.id_number', 'like', "%{$search}%")
                        ->orWhere('centers.name', 'like', "%{$search}%")
                        ->orWhere('groups.name', 'like', "%{$search}%")
                        ->orWhere('plan_types.name', 'like', "%{$search}%")
                        ->orWhere('admins.name', 'like', "%{$search}%");
                });
            })
            ->orderBy($sortColumn, $sortDir);

        $students = $query->paginate($perPage)->withQueryString();
        $students->setCollection(
            $students->getCollection()->map(function ($student) {
                $student->setAttribute('created_at_formatted', $this->dateTimeFormatter->formatForAdmin($student->created_at));

                return $student;
            }),
        );

        return $students;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Student
    {
        return Student::query()->create($this->buildPayload($data));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Student $student, array $data): Student
    {
        $this->dataScope->abortUnlessCanAccessStudent($student);

        $student->update($this->buildPayload($data));

        return $student->refresh();
    }

    public function delete(Student $student): void
    {
        $this->dataScope->abortUnlessCanAccessStudent($student);

        $student->delete();
    }

    /**
     * @return array<int, array{id: int, name: string, working_days: array<int, string>}>
     */
    public function centerOptions(): array
    {
        return Center::query()
            ->tap(fn ($query) => $this->dataScope->applyCenterAccess($query, 'centers'))
            ->orderBy('name')
            ->get(['id', 'name', 'working_days'])
            ->map(static fn (Center $center): array => [
                'id' => (int) $center->id,
                'name' => (string) $center->name,
                'working_days' => is_array($center->working_days) ? $center->working_days : [],
            ])
            ->all();
    }

    /**
     * @return array<int, array{id: int, name: string}>
     */
    public function planOptions(): array
    {
        return Plan::query()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(static fn (Plan $plan): array => [
                'id' => $plan->id,
                'name' => $plan->name,
            ])
            ->all();
    }

    /**
     * @return array<int, array{id: int, plan_id: int, name: string}>
     */
    public function planPointOptions(): array
    {
        return PlanPoint::query()
            ->orderBy('plan_id')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['id', 'plan_id', 'name'])
            ->map(static fn (PlanPoint $point): array => [
                'id' => (int) $point->id,
                'plan_id' => (int) $point->plan_id,
                'name' => (string) $point->name,
            ])
            ->all();
    }

    /**
     * @return array<int, array{id: int, name: string}>
     */
    public function adminOptions(): array
    {
        if ($this->dataScope->shouldScope()) {
            /** @var User|null $user */
            $user = Auth::user();

            return $user === null ? [] : [[
                'id' => $user->id,
                'name' => $user->name !== '' ? $user->name : ($user->email ?? "User #{$user->id}"),
            ]];
        }

        return User::query()
            ->orderBy('name')
            ->get(['id', 'name', 'email'])
            ->map(static fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name !== '' ? $user->name : ($user->email ?? "User #{$user->id}"),
            ])
            ->all();
    }

    public function exportRows(?int $centerId = null): Collection
    {
        return Student::query()
            ->leftJoin('centers', 'students.center_id', '=', 'centers.id')
            ->leftJoin('groups', 'students.group_id', '=', 'groups.id')
            ->leftJoin('plan_types', 'students.plan_type_id', '=', 'plan_types.id')
            ->leftJoin('plan_points as current_plan_points', 'students.current_plan_point_id', '=', 'current_plan_points.id')
            ->leftJoin('users as admins', 'students.admin_id', '=', 'admins.id')
            ->tap(fn ($query) => $this->dataScope->applyStudentAccess($query, 'students'))
            ->when($centerId !== null, static fn ($query) => $query->where('students.center_id', $centerId))
            ->orderBy('students.id')
            ->get([
                'students.id',
                'students.full_name',
                'students.first_name',
                'students.second_name',
                'students.middle_name',
                'students.last_name',
                'students.parent_phone_number',
                'students.phone_number',
                'students.email',
                'students.id_number',
                'students.date_of_birth',
                'centers.name as center_name',
                'students.center_id',
                'groups.name as group_name',
                'students.group_id',
                'plan_types.name as plan_name',
                'students.plan_type_id',
                'current_plan_points.name as plan_point_name',
                'students.current_plan_point_id as plan_point_id',
                'students.points_balance',
                'students.max_daily_weight',
                'students.daily_weight_limits',
                'admins.name as admin_name',
                'students.admin_id',
                'students.is_active',
            ]);
    }

    /**
     * @return array{updated: int, skipped: int, errors: array<int, string>, skipped_rows: array<int, array{line: int, student: string, reason: string}>}
     */
    public function importFile(UploadedFile $file): array
    {
        $import = new StudentsImport(
            currentUserId: Auth::id(),
            canAssignAdmin: (bool) Auth::user()?->hasRole('admin'),
            dataScope: $this->dataScope,
        );

        Excel::import($import, $file);

        return $import->result();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function buildPayload(array $data): array
    {
        $maxDailyWeight = isset($data['max_daily_weight']) ? (int) $data['max_daily_weight'] : 2;
        $workingDays = $this->workingDaysForCenter(isset($data['center_id']) ? (int) $data['center_id'] : null);

        return [
            'first_name' => (string) $data['first_name'],
            'second_name' => (string) $data['second_name'],
            'middle_name' => (string) $data['middle_name'],
            'last_name' => (string) $data['last_name'],
            'full_name' => $this->fullNameFromData($data),
            'id_number' => $data['id_number'] ?? null,
            'parent_phone_number' => PhoneNumberHelper::normalizeForStorage($data['parent_phone_number'] ?? null),
            'phone_number' => PhoneNumberHelper::normalizeForStorage($data['phone_number'] ?? null),
            'email' => $data['email'] ?? null,
            'date_of_birth' => $data['date_of_birth'] ?? null,
            'center_id' => isset($data['center_id']) ? (int) $data['center_id'] : null,
            'group_id' => isset($data['group_id']) ? (int) $data['group_id'] : null,
            'plan_type_id' => isset($data['plan_type_id']) ? (int) $data['plan_type_id'] : null,
            'current_plan_point_id' => isset($data['current_plan_point_id']) ? (int) $data['current_plan_point_id'] : null,
            'max_daily_weight' => $maxDailyWeight,
            'daily_weight_limits' => DailyWeightLimits::normalize($data['daily_weight_limits'] ?? null, $maxDailyWeight, $workingDays),
            'points_balance' => (int) ($data['points_balance'] ?? 0),
            'admin_id' => $this->resolveAdminId($data),
            'is_active' => (int) ($data['is_active'] ?? 1),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function fullNameFromData(array $data): string
    {
        return trim(implode(' ', [
            (string) ($data['first_name'] ?? ''),
            (string) ($data['second_name'] ?? ''),
            (string) ($data['middle_name'] ?? ''),
            (string) ($data['last_name'] ?? ''),
        ]));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function resolveAdminId(array $data): ?int
    {
        $currentUserId = Auth::id();
        $isAdmin = (bool) Auth::user()?->hasRole('admin');

        if ($isAdmin) {
            if (isset($data['admin_id']) && $data['admin_id'] !== null) {
                return (int) $data['admin_id'];
            }

            return $currentUserId;
        }

        return $currentUserId;
    }

    /**
     * @return array<int, string>
     */
    private function workingDaysForCenter(?int $centerId): array
    {
        if ($centerId === null) {
            return DailyWeightLimits::days();
        }

        $center = Center::query()->find($centerId, ['id', 'working_days']);

        return DailyWeightLimits::normalizeWorkingDays($center?->working_days);
    }
}
