<?php

use App\Models\AbsenceRuleExecutionLog;
use App\Models\Center;
use App\Models\Evaluation;
use App\Models\EvaluationStudent;
use App\Models\Student;
use App\Models\User;
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
