<?php

use App\Exceptions\WhatsAppMessageSendException;
use App\Models\AbsenceRule;
use App\Models\AbsenceRuleExecutionLog;
use App\Models\Center;
use App\Models\Evaluation;
use App\Models\EvaluationStudent;
use App\Models\Group;
use App\Models\MessageTemplate;
use App\Models\Student;
use App\Models\StudentFreeze;
use App\Models\StudentPointTransaction;
use App\Models\User;
use App\Services\Admin\AbsenceRules\AbsenceAlertExecutionLock;
use App\Services\Admin\AbsenceRules\AbsenceRuleEngine;
use App\Services\Admin\AbsenceRules\MessageDispatchResult;
use App\Services\Admin\HomeworkService;
use App\Services\Admin\WhatsAppMessagingService;
use App\Services\Auth\PermissionSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

test('message dispatch results always snapshot the actual group recipient', function () {
    $groupSerialized = '120363999999999999@g.us';

    expect(MessageDispatchResult::sent($groupSerialized)->meta)
        ->toMatchArray(['group_serialized' => $groupSerialized])
        ->and(MessageDispatchResult::notSent(null)->meta)
        ->toHaveKey('group_serialized', null);
});

test('an evaluation cannot process absence alerts while its execution lock is held', function () {
    $evaluation = Evaluation::factory()->create([
        'is_send_absence_alerts' => false,
    ]);
    $lock = app(AbsenceAlertExecutionLock::class)->acquire($evaluation->id);

    expect($lock)->not->toBeNull();

    try {
        $result = app(AbsenceRuleEngine::class)->process($evaluation);
    } finally {
        $lock?->release();
    }

    expect($result['processed'])->toBe(0)
        ->and($result['errors'])->toBe([__('evaluations.absence_alerts_processing')])
        ->and($evaluation->refresh()->is_send_absence_alerts)->toBeFalse()
        ->and(AbsenceRuleExecutionLog::query()->count())->toBe(0);
});

test('the absence execution lock lease can be refreshed for long evaluations', function () {
    $clock = Carbon::parse('2026-08-30 12:00:00');
    Carbon::setTestNow($clock);
    $lockService = app(AbsenceAlertExecutionLock::class);
    $lock = $lockService->acquire(987654);

    expect($lock)->not->toBeNull();

    try {
        Carbon::setTestNow($clock->copy()->addSeconds(590));

        expect($lockService->refresh($lock))->toBeTrue();

        Carbon::setTestNow($clock->copy()->addSeconds(610));

        expect($lockService->acquire(987654))->toBeNull();
    } finally {
        $lock?->release();
        Carbon::setTestNow();
    }
});

test('admin can create an alert only absence rule', function () {
    app(PermissionSyncService::class)->sync();

    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $center = Center::factory()->create();
    $template = MessageTemplate::factory()->create();

    $this->actingAs($admin, 'web')
        ->post('/admin/absence-rules', [
            'center_id' => $center->id,
            'attendance_type' => AbsenceRule::ATTENDANCE_TYPE_ABSENCE,
            'occurrence_number' => 1,
            'action' => AbsenceRule::ACTION_SEND_MESSAGE,
            'message_template_id' => $template->id,
            'send_to_center_group' => false,
            'deduction_points_count' => 25,
            'is_active' => true,
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect('/admin/absence-rules');

    $this->assertDatabaseHas('absence_rules', [
        'center_id' => $center->id,
        'attendance_type' => AbsenceRule::ATTENDANCE_TYPE_ABSENCE,
        'occurrence_number' => 1,
        'action' => AbsenceRule::ACTION_SEND_MESSAGE,
        'message_template_id' => $template->id,
        'deduction_points_count' => 25,
        'freeze_reason' => null,
        'freeze_working_days_count' => 4,
        'is_active' => true,
    ]);
});

test('admin can create rules for present and late attendance', function (string $attendanceType) {
    app(PermissionSyncService::class)->sync();

    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $center = Center::factory()->create();
    $template = MessageTemplate::factory()->create();

    $this->actingAs($admin, 'web')
        ->post('/admin/absence-rules', [
            'center_id' => $center->id,
            'attendance_type' => $attendanceType,
            'occurrence_number' => 1,
            'action' => AbsenceRule::ACTION_SEND_MESSAGE,
            'message_template_id' => $template->id,
            'send_to_center_group' => false,
            'deduction_points_count' => 0,
            'is_active' => true,
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect('/admin/absence-rules');

    $this->assertDatabaseHas('absence_rules', [
        'center_id' => $center->id,
        'attendance_type' => $attendanceType,
        'occurrence_number' => 1,
    ]);
})->with([
    'present' => AbsenceRule::ATTENDANCE_TYPE_PRESENT,
    'late' => AbsenceRule::ATTENDANCE_TYPE_LATE,
]);

test('alert only absence rule sends the message and deducts configured points without freezing', function () {
    $messaging = new class extends WhatsAppMessagingService
    {
        /** @var array<int, array{phones: array<int, string>, content: string, group_serialized: string|null}> */
        public array $calls = [];

        public function sendMediaCaption(array $phones, string $content, ?string $groupSerialized = null): void
        {
            $this->calls[] = [
                'phones' => $phones,
                'content' => $content,
                'group_serialized' => $groupSerialized,
            ];
        }
    };

    app()->instance(WhatsAppMessagingService::class, $messaging);

    $center = Center::factory()->create(['group_serialized' => null]);
    $student = Student::factory()
        ->active()
        ->create([
            'center_id' => $center->id,
            'deducted_points_count' => 7,
            'points_balance' => 100,
        ]);
    $template = MessageTemplate::factory()->create([
        'content' => 'Alert for {{full_name}} on {{date}}.',
    ]);
    $evaluation = Evaluation::factory()->create([
        'center_id' => $center->id,
        'date' => '2026-07-08',
        'is_send_absence_alerts' => false,
    ]);

    $evaluationStudent = EvaluationStudent::factory()
        ->absence()
        ->create([
            'evaluation_id' => $evaluation->id,
            'student_id' => $student->id,
            'user_id' => $student->id,
        ]);

    $rule = AbsenceRule::factory()
        ->sendMessageAction()
        ->create([
            'center_id' => $center->id,
            'attendance_type' => AbsenceRule::ATTENDANCE_TYPE_ABSENCE,
            'occurrence_number' => 1,
            'message_template_id' => $template->id,
            'deduction_points_count' => 25,
            'is_active' => true,
        ]);

    $result = app(AbsenceRuleEngine::class)->process($evaluation);

    expect($result['processed'])->toBe(1)
        ->and($result['errors'])->toBe([])
        ->and($messaging->calls)->toHaveCount(1);

    $log = AbsenceRuleExecutionLog::query()->firstOrFail();

    expect($log->action)->toBe(AbsenceRule::ACTION_SEND_MESSAGE)
        ->and($log->absence_rule_id)->toBe($rule->id)
        ->and($log->was_message_sent)->toBeTrue()
        ->and($log->student_was_frozen)->toBeFalse()
        ->and($log->student_was_dismissed)->toBeFalse()
        ->and($log->deduction_points_count)->toBe(25)
        ->and(StudentFreeze::query()->count())->toBe(0)
        ->and($student->refresh()->is_active)->toBe(Student::STATUS_ACTIVE)
        ->and($student->deducted_points_count)->toBe(32)
        ->and($student->points_balance)->toBe(75);

    $transaction = StudentPointTransaction::query()->sole();
    expect($transaction->type)->toBe(StudentPointTransaction::TYPE_ATTENDANCE_RULE_DEDUCTION)
        ->and($transaction->evaluation_id)->toBe($evaluation->id)
        ->and($transaction->evaluation_student_id)->toBe($evaluationStudent->id)
        ->and($transaction->absence_rule_id)->toBe($rule->id)
        ->and($transaction->points)->toBe(-25)
        ->and($transaction->balance_before)->toBe(100)
        ->and($transaction->balance_after)->toBe(75)
        ->and(app(HomeworkService::class)->pointHistory($student)[0]['plan_point_name'])
        ->toBe(__('homeworks.attendance_rule_deduction', [
            'attendance' => __('homeworks.attendance_absence'),
        ]));

    app(PermissionSyncService::class)->sync();
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $this->actingAs($admin, 'web')
        ->getJson(route('admin.students.point-history', $student))
        ->assertOk()
        ->assertJsonPath('data.0.points', -25)
        ->assertJsonPath('data.0.balance_after', 75)
        ->assertJsonPath('data.0.plan_point_name', __('homeworks.attendance_rule_deduction', [
            'attendance' => __('homeworks.attendance_absence'),
        ]));

    $evaluation->update(['is_send_absence_alerts' => false]);
    app(AbsenceRuleEngine::class)->process($evaluation->refresh());

    expect(StudentPointTransaction::query()->count())->toBe(1)
        ->and($student->refresh()->points_balance)->toBe(75);
});

test('late attendance triggers its matching monthly rule', function () {
    $messaging = new class extends WhatsAppMessagingService
    {
        /** @var array<int, string> */
        public array $messages = [];

        public function sendMediaCaption(array $phones, string $content, ?string $groupSerialized = null): void
        {
            $this->messages[] = $content;
        }
    };
    app()->instance(WhatsAppMessagingService::class, $messaging);

    $center = Center::factory()->create();
    $student = Student::factory()->active()->create(['center_id' => $center->id]);
    $evaluation = Evaluation::factory()->create([
        'center_id' => $center->id,
        'date' => '2026-07-08',
        'is_send_absence_alerts' => false,
    ]);
    EvaluationStudent::factory()->late()->create([
        'evaluation_id' => $evaluation->id,
        'student_id' => $student->id,
        'user_id' => $student->id,
    ]);
    $template = MessageTemplate::factory()->create([
        'content' => '{{attendance.label_ar}} - {{attendance.occurrence_number}}',
    ]);
    AbsenceRule::factory()->sendMessageAction()->create([
        'center_id' => $center->id,
        'attendance_type' => AbsenceRule::ATTENDANCE_TYPE_LATE,
        'occurrence_number' => 1,
        'message_template_id' => $template->id,
        'deduction_points_count' => 0,
    ]);

    $result = app(AbsenceRuleEngine::class)->process($evaluation);

    expect($result['processed'])->toBe(1)
        ->and($result['errors'])->toBe([])
        ->and($messaging->messages)->toBe(['متأخر - 1'])
        ->and(AbsenceRuleExecutionLog::query()->sole()->attendance_type)
        ->toBe(AbsenceRule::ATTENDANCE_TYPE_LATE);
});

test('group evaluation drives the rule center group message template and freeze schedule', function () {
    app()->detectEnvironment(fn (): string => 'testing');

    $messaging = new class extends WhatsAppMessagingService
    {
        /** @var array<int, array{phones: array<int, string>, content: string, group_serialized: string|null}> */
        public array $calls = [];

        public function sendMediaCaption(array $phones, string $content, ?string $groupSerialized = null): void
        {
            $this->calls[] = [
                'phones' => $phones,
                'content' => $content,
                'group_serialized' => $groupSerialized,
            ];
        }
    };

    app()->instance(WhatsAppMessagingService::class, $messaging);

    $legacyCenter = Center::factory()->create([
        'name' => 'Legacy student center',
        'phone' => '+962790000001',
        'group_serialized' => '120363111111111111@g.us',
        'working_days' => ['thursday'],
    ]);
    $groupCenter = Center::factory()->create([
        'name' => 'Group center',
        'phone' => '+962790000002',
        'group_serialized' => '120363222222222222@g.us',
        'working_days' => ['thursday'],
    ]);
    $group = Group::factory()->create([
        'name' => 'مجموعة الاختبار',
        'center_id' => $groupCenter->id,
        'group_serialized' => '120363333333333333@g.us',
        'working_days' => ['monday'],
    ]);
    $student = Student::factory()
        ->active()
        ->create([
            'center_id' => $legacyCenter->id,
            'group_id' => $group->id,
        ]);
    $template = MessageTemplate::factory()->create([
        'content' => 'تنبيه {{group.name}} في {{center.name}}',
    ]);
    $evaluation = Evaluation::factory()->create([
        'center_id' => $legacyCenter->id,
        'group_id' => $group->id,
        'date' => '2026-07-08',
        'is_send_absence_alerts' => false,
    ]);

    EvaluationStudent::factory()
        ->absence()
        ->create([
            'evaluation_id' => $evaluation->id,
            'student_id' => $student->id,
            'user_id' => $student->id,
        ]);

    $rule = AbsenceRule::factory()
        ->freezeAction()
        ->create([
            'center_id' => $groupCenter->id,
            'attendance_type' => AbsenceRule::ATTENDANCE_TYPE_ABSENCE,
            'occurrence_number' => 1,
            'message_template_id' => $template->id,
            'send_to_center_group' => true,
            'freeze_working_days_count' => 2,
            'deduction_points_count' => 0,
            'is_active' => true,
        ]);

    $result = app(AbsenceRuleEngine::class)->processEvaluation($evaluation->id);

    expect($result['processed'])->toBe(1)
        ->and($result['errors'])->toBe([])
        ->and($messaging->calls)->toHaveCount(1)
        ->and($messaging->calls[0]['group_serialized'])->toBe($group->group_serialized)
        ->and($messaging->calls[0]['content'])->toContain($group->name)
        ->and($messaging->calls[0]['content'])->toContain($groupCenter->name);

    $log = AbsenceRuleExecutionLog::query()->firstOrFail();
    $freeze = StudentFreeze::query()->firstOrFail();

    expect($log->absence_rule_id)->toBe($rule->id)
        ->and($log->center_id)->toBe($groupCenter->id)
        ->and($log->sent_to_group)->toBeTrue()
        ->and($log->meta)->toHaveKey('group_serialized', $group->group_serialized)
        ->and($freeze->from->toDateString())->toBe('2026-07-09')
        ->and($freeze->to->toDateString())->toBe('2026-07-20')
        ->and($freeze->contact_phone)->toBe($groupCenter->phone)
        ->and($student->refresh()->is_active)->toBe(Student::STATUS_FROZEN);
});

test('dismiss absence rule sends the message deducts points and marks the student inactive', function () {
    app()->detectEnvironment(fn (): string => 'testing');

    $messaging = new class extends WhatsAppMessagingService
    {
        /** @var array<int, array{phones: array<int, string>, content: string, group_serialized: string|null}> */
        public array $calls = [];

        public function sendMediaCaption(array $phones, string $content, ?string $groupSerialized = null): void
        {
            $this->calls[] = [
                'phones' => $phones,
                'content' => $content,
                'group_serialized' => $groupSerialized,
            ];
        }
    };

    app()->instance(WhatsAppMessagingService::class, $messaging);

    $center = Center::factory()->create(['group_serialized' => null]);
    $student = Student::factory()
        ->active()
        ->create([
            'center_id' => $center->id,
            'deducted_points_count' => 4,
        ]);
    $template = MessageTemplate::factory()->create([
        'content' => 'قرار فصل للطالب: *{ student.full_name }* - الرصيد بعد الخصم: { student.deducted_points_after }',
    ]);
    $evaluation = Evaluation::factory()->create([
        'center_id' => $center->id,
        'date' => '2026-07-08',
        'is_send_absence_alerts' => false,
    ]);

    EvaluationStudent::factory()
        ->absence()
        ->create([
            'evaluation_id' => $evaluation->id,
            'student_id' => $student->id,
            'user_id' => $student->id,
        ]);

    $rule = AbsenceRule::factory()
        ->dismissAction()
        ->create([
            'center_id' => $center->id,
            'attendance_type' => AbsenceRule::ATTENDANCE_TYPE_ABSENCE,
            'occurrence_number' => 1,
            'message_template_id' => $template->id,
            'deduction_points_count' => 30,
            'is_active' => true,
        ]);

    $result = app(AbsenceRuleEngine::class)->process($evaluation);

    expect($result['processed'])->toBe(1)
        ->and($result['errors'])->toBe([])
        ->and($result['alerts_marked_as_sent'])->toBeTrue()
        ->and($messaging->calls)->toHaveCount(1)
        ->and($messaging->calls[0]['content'])->toContain("*{$student->full_name}*")
        ->and($messaging->calls[0]['content'])->toContain('34');

    $log = AbsenceRuleExecutionLog::query()->firstOrFail();

    expect($log->action)->toBe(AbsenceRule::ACTION_DISMISS_STUDENT)
        ->and($log->absence_rule_id)->toBe($rule->id)
        ->and($log->was_message_sent)->toBeTrue()
        ->and($log->student_was_frozen)->toBeFalse()
        ->and($log->student_was_dismissed)->toBeTrue()
        ->and($log->deduction_points_count)->toBe(30)
        ->and($log->message_content)->toContain("*{$student->full_name}*")
        ->and($student->refresh()->is_active)->toBe(Student::STATUS_INACTIVE)
        ->and($student->deducted_points_count)->toBe(34)
        ->and($evaluation->refresh()->is_send_absence_alerts)->toBeTrue();
});

test('local environment creates absence alert previews without sending or mutating students', function () {
    app()->detectEnvironment(fn (): string => 'local');

    $messaging = new class extends WhatsAppMessagingService
    {
        public int $calls = 0;

        public function sendMediaCaption(array $phones, string $content, ?string $groupSerialized = null): void
        {
            $this->calls++;
        }
    };

    app()->instance(WhatsAppMessagingService::class, $messaging);

    $center = Center::factory()->create(['group_serialized' => '120363000000000000@g.us']);
    $student = Student::factory()
        ->active()
        ->create([
            'center_id' => $center->id,
            'deducted_points_count' => 7,
        ]);
    $template = MessageTemplate::factory()->create([
        'content' => 'تنبيه للطالب: *{ student.full_name }*',
    ]);
    $evaluation = Evaluation::factory()->create([
        'center_id' => $center->id,
        'date' => '2026-07-08',
        'is_send_absence_alerts' => false,
    ]);

    EvaluationStudent::factory()
        ->absence()
        ->create([
            'evaluation_id' => $evaluation->id,
            'student_id' => $student->id,
            'user_id' => $student->id,
        ]);

    AbsenceRule::factory()
        ->sendMessageAction()
        ->create([
            'center_id' => $center->id,
            'attendance_type' => AbsenceRule::ATTENDANCE_TYPE_ABSENCE,
            'occurrence_number' => 1,
            'message_template_id' => $template->id,
            'send_to_center_group' => true,
            'deduction_points_count' => 25,
            'is_active' => true,
        ]);

    $result = app(AbsenceRuleEngine::class)->process($evaluation);

    expect($result['processed'])->toBe(1)
        ->and($result['errors'])->toBe([])
        ->and($result['local_preview'])->toBeTrue()
        ->and($result['alerts_marked_as_sent'])->toBeFalse()
        ->and($result['preview_messages'])->toHaveCount(1)
        ->and($messaging->calls)->toBe(0);

    $log = AbsenceRuleExecutionLog::query()->firstOrFail();

    expect($log->was_message_sent)->toBeFalse()
        ->and($log->meta['local_preview'])->toBeTrue()
        ->and($log->meta['whatsapp_skipped'])->toBeTrue()
        ->and($log->message_content)->toContain("*{$student->full_name}*")
        ->and($log->message_content)->not->toContain('{ student.full_name }')
        ->and($evaluation->refresh()->is_send_absence_alerts)->toBeFalse()
        ->and($student->refresh()->deducted_points_count)->toBe(7);
});

test('absence rule occurrence number is counted within the evaluation month', function () {
    $messaging = new class extends WhatsAppMessagingService
    {
        /** @var array<int, array{phones: array<int, string>, content: string, group_serialized: string|null}> */
        public array $calls = [];

        public function sendMediaCaption(array $phones, string $content, ?string $groupSerialized = null): void
        {
            $this->calls[] = [
                'phones' => $phones,
                'content' => $content,
                'group_serialized' => $groupSerialized,
            ];
        }
    };

    app()->instance(WhatsAppMessagingService::class, $messaging);

    $center = Center::factory()->create(['group_serialized' => null]);
    $group = Group::factory()->create(['center_id' => $center->id]);
    $otherGroup = Group::factory()->create(['center_id' => $center->id]);
    $student = Student::factory()
        ->active()
        ->create([
            'center_id' => $center->id,
            'group_id' => $group->id,
        ]);
    $template = MessageTemplate::factory()->create([
        'content' => 'تنبيه رقم {{ occurrence_number }} لهذا الشهر في {{ group.name }}',
    ]);

    foreach ([
        ['date' => '2026-06-30', 'group_id' => $group->id],
        ['date' => '2026-07-01', 'group_id' => $group->id],
        ['date' => '2026-07-03', 'group_id' => $otherGroup->id],
        ['date' => '2026-07-05', 'group_id' => $group->id],
    ] as $prior) {
        $priorEvaluation = Evaluation::factory()->create([
            'center_id' => $center->id,
            'group_id' => $prior['group_id'],
            'date' => $prior['date'],
            'is_send_absence_alerts' => true,
        ]);

        EvaluationStudent::factory()
            ->absence()
            ->create([
                'evaluation_id' => $priorEvaluation->id,
                'student_id' => $student->id,
                'user_id' => $student->id,
            ]);
    }

    $currentEvaluation = Evaluation::factory()->create([
        'center_id' => $center->id,
        'group_id' => $group->id,
        'date' => '2026-07-08',
        'is_send_absence_alerts' => false,
    ]);

    EvaluationStudent::factory()
        ->absence()
        ->create([
            'evaluation_id' => $currentEvaluation->id,
            'student_id' => $student->id,
            'user_id' => $student->id,
        ]);

    $rule = AbsenceRule::factory()
        ->sendMessageAction()
        ->create([
            'center_id' => $center->id,
            'attendance_type' => AbsenceRule::ATTENDANCE_TYPE_ABSENCE,
            'occurrence_number' => 3,
            'message_template_id' => $template->id,
            'is_active' => true,
        ]);

    $result = app(AbsenceRuleEngine::class)->process($currentEvaluation);

    expect($result['processed'])->toBe(1)
        ->and($result['errors'])->toBe([])
        ->and($messaging->calls)->toHaveCount(1)
        ->and($messaging->calls[0]['content'])->toContain('تنبيه رقم 3 لهذا الشهر')
        ->and($messaging->calls[0]['content'])->toContain($group->name);

    $log = AbsenceRuleExecutionLog::query()->firstOrFail();

    expect($log->absence_rule_id)->toBe($rule->id)
        ->and($log->occurrence_number)->toBe(3)
        ->and($log->message_content)->toContain('تنبيه رقم 3 لهذا الشهر');
});

test('retrying an evaluation skips completed rows and only retries a proven zero delivery failure', function () {
    app()->detectEnvironment(fn (): string => 'testing');

    $messaging = new class extends WhatsAppMessagingService
    {
        /** @var array<int, array<int, string>> */
        public array $calls = [];

        private bool $failedSecondStudentOnce = false;

        public function sendMediaCaption(array $phones, string $content, ?string $groupSerialized = null): void
        {
            $this->calls[] = $phones;

            if (($phones[0] ?? null) === '962790000002' && ! $this->failedSecondStudentOnce) {
                $this->failedSecondStudentOnce = true;

                throw new WhatsAppMessageSendException(
                    'No recipient was reached.',
                    ['962790000002@s.whatsapp.net'],
                );
            }
        }
    };
    app()->instance(WhatsAppMessagingService::class, $messaging);

    $center = Center::factory()->create();
    $group = Group::factory()->create([
        'center_id' => $center->id,
        'group_serialized' => null,
    ]);
    $evaluation = Evaluation::factory()->create([
        'center_id' => $center->id,
        'group_id' => $group->id,
        'date' => '2026-07-08',
        'is_send_absence_alerts' => false,
    ]);

    foreach (['962790000001', '962790000002'] as $phone) {
        $student = Student::factory()->active()->create([
            'center_id' => $center->id,
            'group_id' => $group->id,
            'parent_phone_number' => $phone,
            'phone_number' => null,
        ]);

        EvaluationStudent::factory()->absence()->create([
            'evaluation_id' => $evaluation->id,
            'student_id' => $student->id,
            'user_id' => $student->id,
        ]);
    }

    AbsenceRule::factory()->sendMessageAction()->create([
        'center_id' => $center->id,
        'attendance_type' => AbsenceRule::ATTENDANCE_TYPE_ABSENCE,
        'occurrence_number' => 1,
        'message_template_id' => MessageTemplate::factory(),
        'deduction_points_count' => 0,
        'is_active' => true,
    ]);

    $firstResult = app(AbsenceRuleEngine::class)->process($evaluation);

    expect($firstResult['processed'])->toBe(1)
        ->and($firstResult['errors'])->toHaveCount(1)
        ->and($firstResult['alerts_marked_as_sent'])->toBeFalse()
        ->and($messaging->calls)->toHaveCount(2)
        ->and(AbsenceRuleExecutionLog::query()->count())->toBe(2);

    $secondResult = app(AbsenceRuleEngine::class)->process($evaluation->refresh());

    expect($secondResult['processed'])->toBe(1)
        ->and($secondResult['skipped'])->toBe(1)
        ->and($secondResult['errors'])->toBe([])
        ->and($secondResult['alerts_marked_as_sent'])->toBeTrue()
        ->and($messaging->calls)->toHaveCount(3)
        ->and(AbsenceRuleExecutionLog::query()->count())->toBe(2)
        ->and($evaluation->refresh()->is_send_absence_alerts)->toBeTrue();
});

test('failed absence message logs snapshot the evaluation group recipient', function () {
    app()->detectEnvironment(fn (): string => 'testing');

    $messaging = new class extends WhatsAppMessagingService
    {
        public int $calls = 0;

        public function sendMediaCaption(array $phones, string $content, ?string $groupSerialized = null): void
        {
            $this->calls++;

            throw new WhatsAppMessageSendException(
                'Forced partial WhatsApp failure.',
                [$groupSerialized],
            );
        }
    };

    app()->instance(WhatsAppMessagingService::class, $messaging);

    $center = Center::factory()->create([
        'group_serialized' => '120363111111111111@g.us',
    ]);
    $group = Group::factory()->create([
        'center_id' => $center->id,
        'group_serialized' => '120363222222222222@g.us',
    ]);
    $student = Student::factory()->active()->create([
        'center_id' => $center->id,
        'group_id' => $group->id,
        'parent_phone_number' => '962790000001',
        'phone_number' => null,
        'deducted_points_count' => 0,
    ]);
    $student->groups()->syncWithoutDetaching([$group->id]);
    $template = MessageTemplate::factory()->create([
        'content' => 'تنبيه للطالب {{ student.full_name }}',
    ]);
    $evaluation = Evaluation::factory()->create([
        'center_id' => $center->id,
        'group_id' => $group->id,
        'date' => '2026-07-08',
        'is_send_absence_alerts' => false,
    ]);

    EvaluationStudent::factory()->absence()->create([
        'evaluation_id' => $evaluation->id,
        'student_id' => $student->id,
        'user_id' => $student->id,
    ]);

    AbsenceRule::factory()->sendMessageAction()->create([
        'center_id' => $center->id,
        'attendance_type' => AbsenceRule::ATTENDANCE_TYPE_ABSENCE,
        'occurrence_number' => 1,
        'message_template_id' => $template->id,
        'send_to_center_group' => true,
        'deduction_points_count' => 0,
        'is_active' => true,
    ]);

    $result = app(AbsenceRuleEngine::class)->process($evaluation);
    $log = AbsenceRuleExecutionLog::query()->firstOrFail();

    expect($result['processed'])->toBe(0)
        ->and($result['errors'])->toHaveCount(1)
        ->and($log->was_message_sent)->toBeFalse()
        ->and($log->meta)->toHaveKey('group_serialized', $group->group_serialized)
        ->and($log->meta['error'])->toBe('Forced partial WhatsApp failure.')
        ->and($log->meta['partial_delivery'])->toBeTrue()
        ->and($log->meta['delivered_chat_ids'])->toBe(['962790000001@s.whatsapp.net'])
        ->and($log->meta['remaining_chat_ids'])->toBe([$group->group_serialized])
        ->and($student->refresh()->deducted_points_count)->toBe(0);

    $retryResult = app(AbsenceRuleEngine::class)->process($evaluation->refresh());

    expect($retryResult['processed'])->toBe(0)
        ->and($retryResult['errors'])->toBe([
            __('evaluations.absence_alert_requires_review', ['item' => $log->evaluation_student_id]),
        ])
        ->and($messaging->calls)->toBe(1)
        ->and(AbsenceRuleExecutionLog::query()->count())->toBe(1)
        ->and($evaluation->refresh()->is_send_absence_alerts)->toBeFalse();
});

test('an ambiguous automatic delivery is marked unsafe and cannot be retried automatically', function () {
    app()->detectEnvironment(fn (): string => 'testing');

    $messaging = new class extends WhatsAppMessagingService
    {
        public int $calls = 0;

        public function sendMediaCaption(array $phones, string $content, ?string $groupSerialized = null): void
        {
            $this->calls++;

            throw new RuntimeException('Ambiguous transport timeout.');
        }
    };
    app()->instance(WhatsAppMessagingService::class, $messaging);

    $center = Center::factory()->create();
    $group = Group::factory()->create(['center_id' => $center->id]);
    $student = Student::factory()->active()->create([
        'center_id' => $center->id,
        'group_id' => $group->id,
        'parent_phone_number' => '962790000001',
        'phone_number' => null,
    ]);
    $template = MessageTemplate::factory()->create(['content' => 'Absence alert.']);
    $evaluation = Evaluation::factory()->create([
        'center_id' => $center->id,
        'group_id' => $group->id,
        'date' => '2026-07-08',
        'is_send_absence_alerts' => false,
    ]);
    $item = EvaluationStudent::factory()->absence()->create([
        'evaluation_id' => $evaluation->id,
        'student_id' => $student->id,
        'user_id' => $student->id,
    ]);
    AbsenceRule::factory()->sendMessageAction()->create([
        'center_id' => $center->id,
        'attendance_type' => AbsenceRule::ATTENDANCE_TYPE_ABSENCE,
        'occurrence_number' => 1,
        'message_template_id' => $template->id,
        'deduction_points_count' => 0,
        'is_active' => true,
    ]);

    $firstResult = app(AbsenceRuleEngine::class)->process($evaluation);
    $log = AbsenceRuleExecutionLog::query()->sole();

    expect($firstResult['errors'])->toHaveCount(1)
        ->and($log->meta['delivery_unknown'])->toBeTrue()
        ->and($log->meta['processing'])->toBeFalse();

    $retryResult = app(AbsenceRuleEngine::class)->process($evaluation->refresh());

    expect($retryResult['processed'])->toBe(0)
        ->and($retryResult['errors'])->toBe([
            __('evaluations.absence_alert_requires_review', ['item' => $item->id]),
        ])
        ->and($messaging->calls)->toBe(1)
        ->and(AbsenceRuleExecutionLog::query()->count())->toBe(1);
});
