<?php

namespace Database\Factories;

use App\Models\Student;
use App\Models\StudentFreeze;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StudentFreeze>
 */
class StudentFreezeFactory extends Factory
{
    protected $model = StudentFreeze::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $from = CarbonImmutable::instance(fake()->dateTimeBetween('-20 days', '-5 days'));
        $to = $from->addDays(fake()->numberBetween(3, 14));

        return [
            'student_id' => Student::factory(),
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'reason' => fake()->sentence(5),
            'contact_phone' => '9627'.fake()->numerify('#######'),
            'frozen_by' => User::factory(),
            'is_active' => true,
            'unfrozen_at' => null,
        ];
    }

    public function inactive(): static
    {
        return $this->state(function (): array {
            $from = CarbonImmutable::now()->subDays(fake()->numberBetween(30, 10));
            $to = $from->addDays(fake()->numberBetween(3, 12));

            return [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'is_active' => false,
                'unfrozen_at' => $to->addDay()->toDateTimeString(),
            ];
        });
    }
}
