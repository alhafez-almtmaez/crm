<?php

use App\Models\AbsenceRule;
use App\Models\AbsenceRuleExecutionLog;
use App\Models\Center;
use App\Models\Evaluation;
use App\Models\EvaluationStudent;
use App\Models\MessageTemplate;
use App\Models\Student;
use App\Models\StudentFreeze;
use App\Models\User;
use App\Services\Admin\AbsenceRules\AbsenceRuleEngine;
use App\Services\Admin\WhatsAppMessagingService;
use App\Services\Auth\PermissionSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

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
        ]);
    $template = MessageTemplate::factory()->create([
        'content' => 'Alert for {{full_name}} on {{date}}.',
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
        ->and($student->deducted_points_count)->toBe(32);
});
