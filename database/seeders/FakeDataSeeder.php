<?php

namespace Database\Seeders;

use App\Models\AbsenceRule;
use App\Models\AbsenceRuleExecutionLog;
use App\Models\Center;
use App\Models\Device;
use App\Models\Evaluation;
use App\Models\EvaluationStudent;
use App\Models\Group;
use App\Models\MessageTemplate;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Student;
use App\Models\StudentFreeze;
use App\Models\SystemSetting;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class FakeDataSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedSystemSettings();
        $this->seedDevices();

        $admins = $this->seedUsers();
        $plans = $this->seedPlans();
        $centers = $this->seedCenters();
        $groupsByCenter = $this->seedGroups($centers);
        $studentsByCenter = $this->seedStudents($centers, $groupsByCenter, $plans, $admins);

        $this->seedStudentRecords($studentsByCenter, $admins);

        $templates = $this->seedMessageTemplates();
        $this->seedAbsenceRules($centers, $templates);
        $this->seedEvaluations($centers, $studentsByCenter, $admins);
        $this->seedAbsenceExecutionLogs($admins);
    }

    private function seedSystemSettings(): void
    {
        if (! SystemSetting::query()->where('key', 'admin_ui')->exists()) {
            SystemSetting::factory()->adminUi()->create();
        }
    }

    private function seedDevices(): void
    {
        if (! Device::query()->where('status', 'CONNECTED')->exists()) {
            Device::factory()
                ->connected()
                ->state(['name' => 'Main Device'])
                ->create();
        }

        if (! Device::query()->where('status', 'PENDING')->exists()) {
            Device::factory()
                ->pending()
                ->state(['name' => 'Backup Device'])
                ->create();
        }
    }

    /**
     * @return Collection<int, User>
     */
    private function seedUsers(): Collection
    {
        $targetAdmins = 5;
        $existingAdmins = User::query()->role('admin')->get();
        $missing = max(0, $targetAdmins - $existingAdmins->count());
        $adminRole = Role::query()->where('name', 'admin')->first();

        if ($missing > 0 && $adminRole) {
            $extraUsers = User::factory()->count($missing)->create();
            foreach ($extraUsers as $user) {
                $user->assignRole($adminRole);
            }
        }

        $admins = User::query()->role('admin')->get();
        if ($admins->isNotEmpty()) {
            return $admins->values();
        }

        return User::query()->get()->values();
    }

    /**
     * @return Collection<int, Plan>
     */
    private function seedPlans(): Collection
    {
        $missing = max(0, 6 - Plan::query()->count());
        if ($missing > 0) {
            Plan::factory()->count($missing)->create();
        }

        return Plan::query()->get()->values();
    }

    /**
     * @return Collection<int, Center>
     */
    private function seedCenters(): Collection
    {
        $missing = max(0, 4 - Center::query()->count());
        if ($missing > 0) {
            Center::factory()->count($missing)->create();
        }

        return Center::query()->get()->values();
    }

    /**
     * @param Collection<int, Center> $centers
     * @return Collection<int, Collection<int, Group>>
     */
    private function seedGroups(Collection $centers): Collection
    {
        $groupsByCenter = collect();

        foreach ($centers as $center) {
            $existing = Group::query()->where('center_id', $center->id)->get();
            $missing = max(0, 3 - $existing->count());

            if ($missing > 0) {
                Group::factory()->count($missing)->for($center)->create();
                $existing = Group::query()->where('center_id', $center->id)->get();
            }

            $groupsByCenter->put((int) $center->id, $existing->values());
        }

        return $groupsByCenter;
    }

    /**
     * @param Collection<int, Center> $centers
     * @param Collection<int, Collection<int, Group>> $groupsByCenter
     * @param Collection<int, Plan> $plans
     * @param Collection<int, User> $admins
     * @return Collection<int, Collection<int, Student>>
     */
    private function seedStudents(
        Collection $centers,
        Collection $groupsByCenter,
        Collection $plans,
        Collection $admins,
    ): Collection {
        $studentsByCenter = collect();

        foreach ($centers as $center) {
            $existing = Student::query()->where('center_id', $center->id)->get();
            $missing = max(0, 20 - $existing->count());
            /** @var Collection<int, Group> $centerGroups */
            $centerGroups = $groupsByCenter->get((int) $center->id, collect());

            if ($missing > 0) {
                Student::factory()
                    ->count($missing)
                    ->state(function () use ($center, $centerGroups, $plans, $admins): array {
                        return [
                            'center_id' => $center->id,
                            'group_id' => $centerGroups->random()->id,
                            'plan_type_id' => $plans->random()->id,
                            'admin_id' => $admins->random()->id,
                            'is_active' => fake()->randomElement([1, 1, 1, 1, 0, 2]),
                        ];
                    })
                    ->create();

                $existing = Student::query()->where('center_id', $center->id)->get();
            }

            $studentsByCenter->put((int) $center->id, $existing->values());
        }

        return $studentsByCenter;
    }

    /**
     * @param Collection<int, Collection<int, Student>> $studentsByCenter
     * @param Collection<int, User> $admins
     */
    private function seedStudentRecords(Collection $studentsByCenter, Collection $admins): void
    {
        /** @var Collection<int, Student> $allStudents */
        $allStudents = $studentsByCenter->flatten(1)->values();

        $frozenStudents = $allStudents
            ->filter(static fn (Student $student): bool => (int) $student->is_active === Student::STATUS_FROZEN)
            ->values();

        if ($frozenStudents->count() < 8) {
            $needed = 8 - $frozenStudents->count();
            $extra = $allStudents
                ->filter(static fn (Student $student): bool => (int) $student->is_active === Student::STATUS_ACTIVE)
                ->shuffle()
                ->take($needed)
                ->values();

            foreach ($extra as $student) {
                $student->update(['is_active' => Student::STATUS_FROZEN]);
            }

            $frozenStudents = $frozenStudents->merge($extra)->values();
        }

        foreach ($frozenStudents->take(8) as $student) {
            $hasActiveFreeze = StudentFreeze::query()
                ->where('student_id', $student->id)
                ->where('is_active', true)
                ->exists();

            if ($hasActiveFreeze) {
                continue;
            }

            StudentFreeze::factory()
                ->for($student)
                ->state([
                    'from' => now()->subDays(fake()->numberBetween(2, 7))->toDateString(),
                    'to' => now()->addDays(fake()->numberBetween(3, 10))->toDateString(),
                    'frozen_by' => $admins->random()->id,
                    'is_active' => true,
                    'unfrozen_at' => null,
                ])
                ->create();
        }

        $inactiveFreezeMissing = max(0, 15 - StudentFreeze::query()->count());
        for ($i = 0; $i < $inactiveFreezeMissing; $i++) {
            StudentFreeze::factory()
                ->inactive()
                ->state([
                    'student_id' => $allStudents->random()->id,
                    'frozen_by' => $admins->random()->id,
                ])
                ->create();
        }

        $congratulatoryMissing = max(0, 25 - \App\Models\StudentCongratulatory::query()->count());
        for ($i = 0; $i < $congratulatoryMissing; $i++) {
            \App\Models\StudentCongratulatory::factory()
                ->state([
                    'student_id' => $allStudents->random()->id,
                    'sent_by' => $admins->random()->id,
                ])
                ->create();
        }
    }

    /**
     * @return Collection<int, MessageTemplate>
     */
    private function seedMessageTemplates(): Collection
    {
        $missing = max(0, 8 - MessageTemplate::query()->count());
        if ($missing > 0) {
            MessageTemplate::factory()->count($missing)->create();
        }

        return MessageTemplate::query()->where('is_active', true)->get()->values();
    }

    /**
     * @param Collection<int, Center> $centers
     * @param Collection<int, MessageTemplate> $templates
     */
    private function seedAbsenceRules(Collection $centers, Collection $templates): void
    {
        $attendanceTypes = [
            AbsenceRule::ATTENDANCE_TYPE_ABSENCE,
            AbsenceRule::ATTENDANCE_TYPE_EXCUSED_ABSENCE,
        ];

        foreach ($attendanceTypes as $attendanceType) {
            for ($occurrence = 1; $occurrence <= 3; $occurrence++) {
                $action = $attendanceType === AbsenceRule::ATTENDANCE_TYPE_ABSENCE && $occurrence === 3
                    ? AbsenceRule::ACTION_DISMISS_STUDENT
                    : AbsenceRule::ACTION_FREEZE_STUDENT;

                $base = AbsenceRule::factory()
                    ->global()
                    ->state([
                        'attendance_type' => $attendanceType,
                        'occurrence_number' => $occurrence,
                        'action' => $action,
                        'message_template_id' => $templates->random()->id,
                        'freeze_reason' => "Auto seeded rule ({$attendanceType} #{$occurrence})",
                    ])
                    ->raw();

                AbsenceRule::query()->firstOrCreate(
                    [
                        'center_id' => null,
                        'attendance_type' => $attendanceType,
                        'occurrence_number' => $occurrence,
                    ],
                    Arr::except($base, ['center_id', 'attendance_type', 'occurrence_number']),
                );
            }
        }

        foreach ($centers as $center) {
            foreach ($attendanceTypes as $attendanceType) {
                $base = AbsenceRule::factory()
                    ->forCenter($center)
                    ->freezeAction()
                    ->state([
                        'attendance_type' => $attendanceType,
                        'occurrence_number' => 1,
                        'message_template_id' => $templates->random()->id,
                        'freeze_reason' => "Center #{$center->id} seeded rule ({$attendanceType})",
                    ])
                    ->raw();

                AbsenceRule::query()->firstOrCreate(
                    [
                        'center_id' => $center->id,
                        'attendance_type' => $attendanceType,
                        'occurrence_number' => 1,
                    ],
                    Arr::except($base, ['center_id', 'attendance_type', 'occurrence_number']),
                );
            }
        }
    }

    /**
     * @param Collection<int, Center> $centers
     * @param Collection<int, Collection<int, Student>> $studentsByCenter
     * @param Collection<int, User> $admins
     */
    private function seedEvaluations(
        Collection $centers,
        Collection $studentsByCenter,
        Collection $admins,
    ): void {
        $dates = collect(range(0, 7))
            ->map(static fn (int $offset): string => now()->subDays($offset)->toDateString())
            ->reverse()
            ->values();

        $freezesByStudent = StudentFreeze::query()
            ->get()
            ->groupBy('student_id');

        foreach ($centers as $center) {
            /** @var Collection<int, Student> $centerStudents */
            $centerStudents = $studentsByCenter
                ->get((int) $center->id, collect())
                ->filter(static fn (Student $student): bool => (int) $student->is_active !== Student::STATUS_INACTIVE)
                ->values();

            foreach ($dates as $date) {
                $defaults = Evaluation::factory()
                    ->for($center)
                    ->state([
                        'date' => $date,
                        'admin_id' => $admins->random()->id,
                        'is_send_absence_alerts' => fake()->boolean(35),
                        'ulid' => (string) Str::ulid(),
                    ])
                    ->raw();

                $evaluation = Evaluation::query()->firstOrCreate(
                    [
                        'center_id' => $center->id,
                        'date' => $date,
                    ],
                    Arr::except($defaults, ['center_id', 'date']),
                );

                if ($evaluation->evaluationStudents()->exists()) {
                    continue;
                }

                foreach ($centerStudents as $student) {
                    $isFrozenOnDate = $this->isStudentFrozenOnDate((int) $student->id, $date, $freezesByStudent);
                    $attendance = $isFrozenOnDate
                        ? EvaluationStudent::ATTENDANCE_FROZEN
                        : fake()->randomElement([1, 1, 1, 1, 2, 3, 5]);

                    $builder = EvaluationStudent::factory()
                        ->for($evaluation)
                        ->for($student, 'student')
                        ->state(['user_id' => $student->id]);

                    if ($attendance === EvaluationStudent::ATTENDANCE_FROZEN) {
                        $builder->frozen()->create();
                        continue;
                    }

                    if ($attendance === EvaluationStudent::ATTENDANCE_EXCUSED_ABSENCE) {
                        $builder->excusedAbsence()->create();
                        continue;
                    }

                    if ($attendance === EvaluationStudent::ATTENDANCE_ABSENCE) {
                        $builder->absence()->create();
                        continue;
                    }

                    if ($attendance === EvaluationStudent::ATTENDANCE_EXEMPT) {
                        $builder->exempt()->create();
                        continue;
                    }

                    $builder->present()->create();
                }
            }
        }
    }

    /**
     * @param Collection<int, User> $admins
     */
    private function seedAbsenceExecutionLogs(Collection $admins): void
    {
        $targetLogs = 100;
        $existing = AbsenceRuleExecutionLog::query()->count();
        $missing = max(0, $targetLogs - $existing);

        if ($missing === 0) {
            return;
        }

        $candidates = EvaluationStudent::query()
            ->whereIn('attendances', [
                EvaluationStudent::ATTENDANCE_EXCUSED_ABSENCE,
                EvaluationStudent::ATTENDANCE_ABSENCE,
            ])
            ->with(['evaluation', 'student'])
            ->inRandomOrder()
            ->limit($missing * 3)
            ->get();

        $created = 0;

        foreach ($candidates as $item) {
            if ($created >= $missing) {
                break;
            }

            if (! $item->evaluation || ! $item->student) {
                continue;
            }

            $attendanceType = (int) $item->attendances === EvaluationStudent::ATTENDANCE_EXCUSED_ABSENCE
                ? AbsenceRule::ATTENDANCE_TYPE_EXCUSED_ABSENCE
                : AbsenceRule::ATTENDANCE_TYPE_ABSENCE;

            $occurrenceNumber = fake()->numberBetween(1, 3);
            $centerId = (int) ($item->evaluation->center_id ?? 0);

            $rule = AbsenceRule::query()
                ->where('center_id', $centerId)
                ->where('attendance_type', $attendanceType)
                ->where('occurrence_number', $occurrenceNumber)
                ->first();

            if (! $rule) {
                $rule = AbsenceRule::query()
                    ->whereNull('center_id')
                    ->where('attendance_type', $attendanceType)
                    ->where('occurrence_number', $occurrenceNumber)
                    ->first();
            }

            if (! $rule) {
                continue;
            }

            $template = MessageTemplate::query()->find($rule->message_template_id);
            $phones = array_values(array_filter([
                $item->student->parent_phone_number,
                $item->student->phone_number,
            ]));

            $freezeRecordId = null;
            $studentWasFrozen = false;

            if ($rule->action === AbsenceRule::ACTION_FREEZE_STUDENT && fake()->boolean(30)) {
                $from = CarbonImmutable::parse((string) $item->evaluation->date)->addDay();
                $to = $from->addDays(fake()->numberBetween(3, 8));

                $freeze = StudentFreeze::factory()
                    ->inactive()
                    ->state([
                        'student_id' => $item->student_id,
                        'from' => $from->toDateString(),
                        'to' => $to->toDateString(),
                        'frozen_by' => $admins->random()->id,
                    ])
                    ->create();

                $freezeRecordId = $freeze->id;
                $studentWasFrozen = true;
            }

            $snapshot = $template?->content ?? 'Seeded message template snapshot.';
            $message = strtr($snapshot, [
                '{{full_name}}' => (string) $item->student->full_name,
                '{{attendance_label_ar}}' => $attendanceType,
                '{{occurrence_number}}' => (string) $occurrenceNumber,
                '{{date}}' => CarbonImmutable::parse((string) $item->evaluation->date)->toDateString(),
                '{{center_name}}' => (string) ($item->evaluation->center?->name ?? ''),
            ]);

            $factory = AbsenceRuleExecutionLog::factory()->state([
                'evaluation_id' => $item->evaluation_id,
                'evaluation_student_id' => $item->id,
                'student_id' => $item->student_id,
                'center_id' => $centerId > 0 ? $centerId : null,
                'absence_rule_id' => $rule->id,
                'message_template_id' => $rule->message_template_id,
                'attendance_type' => $attendanceType,
                'attendance_value' => (int) $item->attendances,
                'occurrence_number' => $occurrenceNumber,
                'action' => $rule->action,
                'recipient_phones' => $phones,
                'message_template_snapshot' => $snapshot,
                'message_content' => $message,
                'sent_to_group' => (bool) $rule->send_to_center_group,
                'was_message_sent' => true,
                'student_was_frozen' => $studentWasFrozen,
                'student_was_dismissed' => $rule->action === AbsenceRule::ACTION_DISMISS_STUDENT && fake()->boolean(35),
                'student_freeze_id' => $freezeRecordId,
                'deduction_points_count' => (int) $rule->deduction_points_count,
                'executed_by' => $admins->random()->id,
                'executed_at' => CarbonImmutable::parse((string) $item->evaluation->date)
                    ->addHours(fake()->numberBetween(1, 20))
                    ->toDateTimeString(),
                'meta' => [
                    'seeded' => true,
                    'seeded_through' => 'factories',
                ],
            ]);

            if (fake()->boolean(10)) {
                $factory = $factory->failed();
            }

            $factory->create();
            $created++;
        }
    }

    /**
     * @param Collection<int, Collection<int, StudentFreeze>> $freezesByStudent
     */
    private function isStudentFrozenOnDate(
        int $studentId,
        string $date,
        Collection $freezesByStudent,
    ): bool {
        /** @var Collection<int, StudentFreeze> $rows */
        $rows = $freezesByStudent->get($studentId, collect());

        $targetDate = CarbonImmutable::parse($date)->toDateString();

        foreach ($rows as $freeze) {
            $from = CarbonImmutable::parse((string) $freeze->from)->toDateString();
            $to = CarbonImmutable::parse((string) $freeze->to)->toDateString();

            if ($targetDate < $from || $targetDate > $to) {
                continue;
            }

            if ((bool) $freeze->is_active) {
                return true;
            }

            if ($freeze->unfrozen_at === null) {
                return true;
            }

            $unfrozenAt = CarbonImmutable::parse((string) $freeze->unfrozen_at)->toDateString();
            if ($unfrozenAt > $targetDate) {
                return true;
            }
        }

        return false;
    }
}
