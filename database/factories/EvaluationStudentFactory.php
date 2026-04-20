<?php

namespace Database\Factories;

use App\Models\Evaluation;
use App\Models\EvaluationStudent;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EvaluationStudent>
 */
class EvaluationStudentFactory extends Factory
{
    protected $model = EvaluationStudent::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'alhifz' => fake()->numberBetween(5, 10),
            'warud' => fake()->numberBetween(5, 10),
            'akhlaqi' => fake()->numberBetween(5, 10),
            'tajwid' => null,
            'note' => fake()->boolean(25) ? fake()->sentence(4) : null,
            'attendances' => EvaluationStudent::ATTENDANCE_PRESENT,
            'student_id' => Student::factory(),
            'user_id' => static fn (array $attributes): ?int => isset($attributes['student_id'])
                ? (int) $attributes['student_id']
                : null,
            'evaluation_id' => Evaluation::factory(),
        ];
    }

    public function present(): static
    {
        return $this->state(fn (): array => [
            'attendances' => EvaluationStudent::ATTENDANCE_PRESENT,
            'alhifz' => fake()->numberBetween(4, 10),
            'warud' => fake()->numberBetween(4, 10),
            'akhlaqi' => fake()->numberBetween(4, 10),
        ]);
    }

    public function excusedAbsence(): static
    {
        return $this->state(fn (): array => [
            'attendances' => EvaluationStudent::ATTENDANCE_EXCUSED_ABSENCE,
            'alhifz' => null,
            'warud' => null,
            'akhlaqi' => null,
            'note' => fake()->sentence(4),
        ]);
    }

    public function absence(): static
    {
        return $this->state(fn (): array => [
            'attendances' => EvaluationStudent::ATTENDANCE_ABSENCE,
            'alhifz' => null,
            'warud' => null,
            'akhlaqi' => null,
            'note' => fake()->sentence(4),
        ]);
    }

    public function frozen(): static
    {
        return $this->state(fn (): array => [
            'attendances' => EvaluationStudent::ATTENDANCE_FROZEN,
            'alhifz' => null,
            'warud' => null,
            'akhlaqi' => null,
            'note' => null,
        ]);
    }

    public function exempt(): static
    {
        return $this->state(fn (): array => [
            'attendances' => EvaluationStudent::ATTENDANCE_EXEMPT,
            'alhifz' => null,
            'warud' => null,
            'akhlaqi' => null,
        ]);
    }
}

