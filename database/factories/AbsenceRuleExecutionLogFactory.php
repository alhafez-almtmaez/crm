<?php

namespace Database\Factories;

use App\Models\AbsenceRule;
use App\Models\AbsenceRuleExecutionLog;
use App\Models\Center;
use App\Models\Evaluation;
use App\Models\EvaluationStudent;
use App\Models\MessageTemplate;
use App\Models\Student;
use App\Models\StudentFreeze;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AbsenceRuleExecutionLog>
 */
class AbsenceRuleExecutionLogFactory extends Factory
{
    protected $model = AbsenceRuleExecutionLog::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $attendanceValue = fake()->randomElement([2, 3]);

        return [
            'evaluation_id' => Evaluation::factory(),
            'evaluation_student_id' => EvaluationStudent::factory(),
            'student_id' => Student::factory(),
            'center_id' => Center::factory(),
            'absence_rule_id' => AbsenceRule::factory(),
            'message_template_id' => MessageTemplate::factory(),
            'attendance_type' => $attendanceValue === 2 ? 'excused_absence' : 'absence',
            'attendance_value' => $attendanceValue,
            'occurrence_number' => fake()->numberBetween(1, 3),
            'action' => fake()->randomElement(['freeze_student', 'dismiss_student']),
            'recipient_phones' => ['9627'.fake()->numerify('#######')],
            'message_template_snapshot' => 'Template snapshot for seeded log.',
            'message_content' => fake()->sentence(12),
            'sent_to_group' => fake()->boolean(35),
            'was_message_sent' => true,
            'student_was_frozen' => fake()->boolean(25),
            'student_was_dismissed' => fake()->boolean(10),
            'student_freeze_id' => null,
            'deduction_points_count' => fake()->numberBetween(0, 10),
            'executed_by' => User::factory(),
            'executed_at' => fake()->dateTimeBetween('-20 days', 'now'),
            'meta' => ['seeded' => true],
        ];
    }

    public function withFreezeRecord(StudentFreeze $freeze): static
    {
        return $this->state(fn (): array => [
            'student_was_frozen' => true,
            'student_freeze_id' => $freeze->id,
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (): array => [
            'was_message_sent' => false,
            'meta' => ['seeded' => true, 'error' => fake()->sentence(4)],
        ]);
    }
}

