<?php

namespace Database\Factories;

use App\Models\AbsenceRule;
use App\Models\Center;
use App\Models\MessageTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AbsenceRule>
 */
class AbsenceRuleFactory extends Factory
{
    protected $model = AbsenceRule::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'center_id' => null,
            'attendance_type' => fake()->randomElement([
                AbsenceRule::ATTENDANCE_TYPE_ABSENCE,
                AbsenceRule::ATTENDANCE_TYPE_EXCUSED_ABSENCE,
            ]),
            'occurrence_number' => fake()->numberBetween(1, 6),
            'action' => fake()->randomElement([
                AbsenceRule::ACTION_FREEZE_STUDENT,
                AbsenceRule::ACTION_DISMISS_STUDENT,
            ]),
            'message_template_id' => MessageTemplate::factory(),
            'send_to_center_group' => fake()->boolean(40),
            'freeze_reason' => fake()->sentence(4),
            'freeze_working_days_count' => fake()->numberBetween(2, 7),
            'deduction_points_count' => fake()->numberBetween(0, 20),
            'meta' => ['seeded' => true],
            'is_active' => true,
        ];
    }

    public function global(): static
    {
        return $this->state(fn (): array => ['center_id' => null]);
    }

    public function forCenter(Center $center): static
    {
        return $this->state(fn (): array => ['center_id' => $center->id]);
    }

    public function freezeAction(): static
    {
        return $this->state(fn (): array => ['action' => AbsenceRule::ACTION_FREEZE_STUDENT]);
    }

    public function dismissAction(): static
    {
        return $this->state(fn (): array => ['action' => AbsenceRule::ACTION_DISMISS_STUDENT]);
    }
}

