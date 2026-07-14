<?php

use App\Models\AbsenceRuleExecutionLog;
use App\Models\Center;
use App\Models\Evaluation;
use App\Models\EvaluationStudent;
use App\Models\Student;
use App\Models\User;
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

test('authorized users can resend failed absence messages using current student contact and center', function () {
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
    $student = Student::factory()
        ->active()
        ->create([
            'center_id' => $oldCenter->id,
            'admin_id' => $sender->id,
            'parent_phone_number' => '962790000000',
            'phone_number' => null,
        ]);
    $evaluation = Evaluation::factory()->create([
        'center_id' => $oldCenter->id,
        'admin_id' => $sender->id,
    ]);
    $evaluationStudent = EvaluationStudent::factory()
        ->absence()
        ->create([
            'evaluation_id' => $evaluation->id,
            'student_id' => $student->id,
            'user_id' => $student->id,
        ]);

    $log = AbsenceRuleExecutionLog::factory()
        ->failed()
        ->create([
            'evaluation_id' => $evaluation->id,
            'evaluation_student_id' => $evaluationStudent->id,
            'student_id' => $student->id,
            'center_id' => $oldCenter->id,
            'recipient_phones' => ['962790000000'],
            'message_content' => 'Saved absence message.',
            'sent_to_group' => true,
            'meta' => ['error' => 'No LID for user'],
        ]);

    $student->update([
        'center_id' => $newCenter->id,
        'parent_phone_number' => '962791111111',
        'phone_number' => '962792222222',
    ]);

    $this->actingAs($sender, 'web')
        ->postJson("/admin/evaluations/{$evaluation->id}/message-logs/{$log->id}/resend")
        ->assertOk()
        ->assertJsonPath('data.id', $log->id)
        ->assertJsonPath('data.was_message_sent', true)
        ->assertJsonPath('data.center_name', 'New center')
        ->assertJsonPath('data.recipient_phones.0', '962791111111')
        ->assertJsonPath('data.recipient_phones.1', '962792222222')
        ->assertJsonPath('data.group_serialized', '120363111111111111@g.us')
        ->assertJsonPath('data.error', null);

    expect($messaging->calls)->toHaveCount(1)
        ->and($messaging->calls[0]['phones'])->toBe(['962791111111', '962792222222'])
        ->and($messaging->calls[0]['content'])->toBe('Saved absence message.')
        ->and($messaging->calls[0]['group_serialized'])->toBe('120363111111111111@g.us');

    $log->refresh();

    expect($log->center_id)->toBe($newCenter->id)
        ->and($log->recipient_phones)->toBe(['962791111111', '962792222222'])
        ->and($log->was_message_sent)->toBeTrue()
        ->and($log->meta['resent'])->toBeTrue()
        ->and($log->meta['error'])->toBeNull();
});
