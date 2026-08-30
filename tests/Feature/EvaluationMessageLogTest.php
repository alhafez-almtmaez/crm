<?php

use App\Exceptions\WhatsAppMessageSendException;
use App\Models\AbsenceRule;
use App\Models\AbsenceRuleExecutionLog;
use App\Models\Center;
use App\Models\Evaluation;
use App\Models\EvaluationStudent;
use App\Models\Group;
use App\Models\Student;
use App\Models\User;
use App\Models\WhatsAppPendingMessage;
use App\Services\Admin\AbsenceRules\AbsenceAlertExecutionLock;
use App\Services\Admin\WhatsAppMessagingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

test('authorized users can view absence message logs for an evaluation', function () {
    Permission::findOrCreate('evaluations.view', 'web');

    $viewer = User::factory()->create();
    $viewer->givePermissionTo('evaluations.view');

    $center = Center::factory()->create([
        'group_serialized' => '120363000000000000@g.us',
    ]);
    $student = Student::factory()
        ->active()
        ->create([
            'center_id' => $center->id,
            'admin_id' => $viewer->id,
        ]);
    $evaluation = Evaluation::factory()->create([
        'center_id' => $center->id,
        'admin_id' => $viewer->id,
    ]);
    $evaluationStudent = EvaluationStudent::factory()
        ->absence()
        ->create([
            'evaluation_id' => $evaluation->id,
            'student_id' => $student->id,
            'user_id' => $student->id,
        ]);

    AbsenceRuleExecutionLog::factory()->create([
        'evaluation_id' => $evaluation->id,
        'evaluation_student_id' => $evaluationStudent->id,
        'student_id' => $student->id,
        'center_id' => $center->id,
        'attendance_type' => 'absence',
        'recipient_phones' => ['962799999999'],
        'message_content' => 'رسالة الغياب التي تم إرسالها',
        'sent_to_group' => true,
        'was_message_sent' => true,
        'meta' => [],
    ]);
    AbsenceRuleExecutionLog::factory()->create();

    $this->actingAs($viewer, 'web')
        ->getJson("/admin/evaluations/{$evaluation->id}/message-logs")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.student_name', $student->full_name)
        ->assertJsonPath('data.0.message_content', 'رسالة الغياب التي تم إرسالها')
        ->assertJsonPath('data.0.recipient_phones.0', '962799999999')
        ->assertJsonPath('data.0.sent_to_group', true)
        ->assertJsonPath('data.0.group_serialized', '120363000000000000@g.us')
        ->assertJsonPath('data.0.was_message_sent', true);
});

test('authorized users can resend failed absence messages using current contact and the evaluation group', function () {
    Permission::findOrCreate('evaluations.update', 'web');

    $sender = User::factory()->create();
    $sender->givePermissionTo('evaluations.update');

    $messaging = new class extends WhatsAppMessagingService
    {
        /** @var array<int, array{phones: array<int, string>, content: string, group_serialized: string|null}> */
        public array $calls = [];

        /**
         * @param  array<int, string>  $phones
         */
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

    $oldCenter = Center::factory()->create([
        'name' => 'Old center',
        'group_serialized' => '120363000000000000@g.us',
    ]);
    $newCenter = Center::factory()->create([
        'name' => 'New center',
        'group_serialized' => '120363111111111111@g.us',
    ]);
    $oldGroup = Group::factory()->create([
        'center_id' => $oldCenter->id,
        'group_serialized' => '120363222222222222@g.us',
    ]);
    $newGroup = Group::factory()->create([
        'center_id' => $newCenter->id,
        'group_serialized' => '120363333333333333@g.us',
    ]);
    $student = Student::factory()
        ->active()
        ->create([
            'center_id' => $oldCenter->id,
            'group_id' => $oldGroup->id,
            'admin_id' => $sender->id,
            'parent_phone_number' => '962790000000',
            'phone_number' => null,
        ]);
    $evaluation = Evaluation::factory()->create([
        'center_id' => $oldCenter->id,
        'group_id' => $oldGroup->id,
        'admin_id' => $sender->id,
    ]);
    $evaluationStudent = EvaluationStudent::factory()
        ->absence()
        ->create([
            'evaluation_id' => $evaluation->id,
            'student_id' => $student->id,
            'user_id' => $student->id,
        ]);
    $rule = AbsenceRule::factory()->sendMessageAction()->create([
        'deduction_points_count' => 0,
    ]);

    $log = AbsenceRuleExecutionLog::factory()
        ->failed()
        ->create([
            'evaluation_id' => $evaluation->id,
            'evaluation_student_id' => $evaluationStudent->id,
            'student_id' => $student->id,
            'center_id' => $oldCenter->id,
            'absence_rule_id' => $rule->id,
            'action' => AbsenceRule::ACTION_SEND_MESSAGE,
            'recipient_phones' => ['962790000000'],
            'message_content' => 'Saved absence message.',
            'sent_to_group' => true,
            'deduction_points_count' => 0,
            'meta' => ['error' => 'No LID for user'],
        ]);

    $pending = WhatsAppPendingMessage::query()->create([
        'chat_ids' => ['120363000000000000@g.us'],
        'content' => 'Saved absence message.',
        'source_type' => WhatsAppPendingMessage::SOURCE_ABSENCE_RULE_EXECUTION_LOG,
        'source_id' => $log->id,
        'status' => WhatsAppPendingMessage::STATUS_PENDING,
        'available_at' => now()->subMinute(),
    ]);

    $student->update([
        'center_id' => $newCenter->id,
        'group_id' => $newGroup->id,
        'parent_phone_number' => '962791111111',
        'phone_number' => '962792222222',
    ]);
    $student->groups()->sync([$newGroup->id]);

    $this->actingAs($sender, 'web')
        ->postJson("/admin/evaluations/{$evaluation->id}/message-logs/{$log->id}/resend")
        ->assertOk()
        ->assertJsonPath('data.id', $log->id)
        ->assertJsonPath('data.was_message_sent', true)
        ->assertJsonPath('data.center_name', 'Old center')
        ->assertJsonPath('data.recipient_phones.0', '962791111111')
        ->assertJsonPath('data.recipient_phones.1', '962792222222')
        ->assertJsonPath('data.group_serialized', '120363222222222222@g.us')
        ->assertJsonPath('data.error', null);

    expect($messaging->calls)->toHaveCount(1)
        ->and($messaging->calls[0]['phones'])->toBe(['962791111111', '962792222222'])
        ->and($messaging->calls[0]['content'])->toBe('Saved absence message.')
        ->and($messaging->calls[0]['group_serialized'])->toBe('120363222222222222@g.us');

    $log->refresh();

    expect($log->center_id)->toBe($oldCenter->id)
        ->and($log->recipient_phones)->toBe(['962791111111', '962792222222'])
        ->and($log->was_message_sent)->toBeTrue()
        ->and($log->meta['resent'])->toBeTrue()
        ->and($log->meta['error'])->toBeNull()
        ->and($pending->refresh()->status)->toBe(WhatsAppPendingMessage::STATUS_STALE)
        ->and($pending->last_error)->toBe('stale: superseded_by_manual_resend');
});

test('manual resend uses the same evaluation execution lock as automatic alerts', function () {
    Permission::findOrCreate('evaluations.update', 'web');

    $sender = User::factory()->create();
    $sender->givePermissionTo('evaluations.update');

    $messaging = new class extends WhatsAppMessagingService
    {
        public int $calls = 0;

        public function sendMediaCaption(array $phones, string $content, ?string $groupSerialized = null): void
        {
            $this->calls++;
        }
    };
    app()->instance(WhatsAppMessagingService::class, $messaging);

    $center = Center::factory()->create();
    $group = Group::factory()->create(['center_id' => $center->id]);
    $student = Student::factory()->active()->create([
        'center_id' => $center->id,
        'group_id' => $group->id,
        'admin_id' => $sender->id,
    ]);
    $evaluation = Evaluation::factory()->create([
        'center_id' => $center->id,
        'group_id' => $group->id,
        'admin_id' => $sender->id,
    ]);
    $evaluationStudent = EvaluationStudent::factory()->absence()->create([
        'evaluation_id' => $evaluation->id,
        'student_id' => $student->id,
        'user_id' => $student->id,
    ]);
    $rule = AbsenceRule::factory()->sendMessageAction()->create([
        'deduction_points_count' => 0,
    ]);
    $log = AbsenceRuleExecutionLog::factory()->failed()->create([
        'evaluation_id' => $evaluation->id,
        'evaluation_student_id' => $evaluationStudent->id,
        'student_id' => $student->id,
        'center_id' => $center->id,
        'absence_rule_id' => $rule->id,
        'action' => AbsenceRule::ACTION_SEND_MESSAGE,
        'message_content' => 'Saved absence message.',
        'deduction_points_count' => 0,
        'meta' => ['error' => 'Initial failure.'],
    ]);
    $lock = app(AbsenceAlertExecutionLock::class)->acquire($evaluation->id);

    expect($lock)->not->toBeNull();

    try {
        $this->actingAs($sender, 'web')
            ->postJson("/admin/evaluations/{$evaluation->id}/message-logs/{$log->id}/resend")
            ->assertUnprocessable()
            ->assertJsonPath('errors.message.0', __('evaluations.absence_alerts_processing'));
    } finally {
        $lock?->release();
    }

    expect($messaging->calls)->toBe(0)
        ->and($log->refresh()->was_message_sent)->toBeFalse();
});

test('partial manual resend is recorded and cannot be retried as a full duplicate', function () {
    Permission::findOrCreate('evaluations.update', 'web');

    $sender = User::factory()->create();
    $sender->givePermissionTo('evaluations.update');

    $messaging = new class extends WhatsAppMessagingService
    {
        public int $calls = 0;

        public function sendMediaCaption(array $phones, string $content, ?string $groupSerialized = null): void
        {
            $this->calls++;

            throw new WhatsAppMessageSendException(
                'Group delivery failed.',
                [$groupSerialized],
            );
        }
    };
    app()->instance(WhatsAppMessagingService::class, $messaging);

    $center = Center::factory()->create();
    $group = Group::factory()->create([
        'center_id' => $center->id,
        'group_serialized' => '120363222222222222@g.us',
    ]);
    $student = Student::factory()->active()->create([
        'center_id' => $center->id,
        'group_id' => $group->id,
        'admin_id' => $sender->id,
        'parent_phone_number' => '962790000001',
        'phone_number' => null,
    ]);
    $evaluation = Evaluation::factory()->create([
        'center_id' => $center->id,
        'group_id' => $group->id,
        'admin_id' => $sender->id,
    ]);
    $evaluationStudent = EvaluationStudent::factory()->absence()->create([
        'evaluation_id' => $evaluation->id,
        'student_id' => $student->id,
        'user_id' => $student->id,
    ]);
    $rule = AbsenceRule::factory()->sendMessageAction()->create([
        'deduction_points_count' => 0,
    ]);
    $log = AbsenceRuleExecutionLog::factory()->failed()->create([
        'evaluation_id' => $evaluation->id,
        'evaluation_student_id' => $evaluationStudent->id,
        'student_id' => $student->id,
        'center_id' => $center->id,
        'absence_rule_id' => $rule->id,
        'action' => AbsenceRule::ACTION_SEND_MESSAGE,
        'message_content' => 'Saved absence message.',
        'sent_to_group' => true,
        'deduction_points_count' => 0,
        'meta' => ['error' => 'Initial failure.'],
    ]);

    $this->actingAs($sender, 'web')
        ->postJson("/admin/evaluations/{$evaluation->id}/message-logs/{$log->id}/resend")
        ->assertUnprocessable()
        ->assertJsonPath('errors.message.0', 'Group delivery failed.');

    $log->refresh();

    expect($messaging->calls)->toBe(1)
        ->and($log->was_message_sent)->toBeFalse()
        ->and($log->meta['partial_delivery'])->toBeTrue()
        ->and($log->meta['delivered_chat_ids'])->toBe(['962790000001@s.whatsapp.net'])
        ->and($log->meta['remaining_chat_ids'])->toBe([$group->group_serialized]);

    $this->actingAs($sender, 'web')
        ->postJson("/admin/evaluations/{$evaluation->id}/message-logs/{$log->id}/resend")
        ->assertUnprocessable()
        ->assertJsonPath('errors.message.0', __('evaluations.absence_message_partially_sent'));

    expect($messaging->calls)->toBe(1);
});

test('an ambiguous manual resend clears stale delivery evidence and cannot be repeated', function () {
    Permission::findOrCreate('evaluations.update', 'web');

    $sender = User::factory()->create();
    $sender->givePermissionTo('evaluations.update');

    $messaging = new class extends WhatsAppMessagingService
    {
        public int $calls = 0;

        public function sendMediaCaption(array $phones, string $content, ?string $groupSerialized = null): void
        {
            $this->calls++;

            throw new RuntimeException('Ambiguous connection timeout.');
        }
    };
    app()->instance(WhatsAppMessagingService::class, $messaging);

    $center = Center::factory()->create();
    $group = Group::factory()->create(['center_id' => $center->id]);
    $student = Student::factory()->active()->create([
        'center_id' => $center->id,
        'group_id' => $group->id,
        'admin_id' => $sender->id,
        'parent_phone_number' => '962790000001',
        'phone_number' => null,
    ]);
    $evaluation = Evaluation::factory()->create([
        'center_id' => $center->id,
        'group_id' => $group->id,
        'admin_id' => $sender->id,
    ]);
    $evaluationStudent = EvaluationStudent::factory()->absence()->create([
        'evaluation_id' => $evaluation->id,
        'student_id' => $student->id,
        'user_id' => $student->id,
    ]);
    $rule = AbsenceRule::factory()->sendMessageAction()->create([
        'deduction_points_count' => 0,
    ]);
    $log = AbsenceRuleExecutionLog::factory()->failed()->create([
        'evaluation_id' => $evaluation->id,
        'evaluation_student_id' => $evaluationStudent->id,
        'student_id' => $student->id,
        'center_id' => $center->id,
        'absence_rule_id' => $rule->id,
        'action' => AbsenceRule::ACTION_SEND_MESSAGE,
        'message_content' => 'Saved absence message.',
        'deduction_points_count' => 0,
        'meta' => [
            'error' => 'No recipient was reached.',
            'group_serialized' => null,
            'partial_delivery' => false,
            'delivered_chat_ids' => [],
            'remaining_chat_ids' => ['962790000001@s.whatsapp.net'],
        ],
    ]);

    $this->actingAs($sender, 'web')
        ->postJson("/admin/evaluations/{$evaluation->id}/message-logs/{$log->id}/resend")
        ->assertUnprocessable()
        ->assertJsonPath('errors.message.0', 'Ambiguous connection timeout.');

    $log->refresh();

    expect($log->meta['delivery_unknown'])->toBeTrue()
        ->and($log->meta)->not->toHaveKeys([
            'partial_delivery',
            'delivered_chat_ids',
            'remaining_chat_ids',
        ]);

    $this->actingAs($sender, 'web')
        ->postJson("/admin/evaluations/{$evaluation->id}/message-logs/{$log->id}/resend")
        ->assertUnprocessable()
        ->assertJsonPath('errors.message.0', __('evaluations.absence_message_delivery_uncertain'));

    expect($messaging->calls)->toBe(1);
});
