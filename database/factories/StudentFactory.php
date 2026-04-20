<?php

namespace Database\Factories;

use App\Models\Center;
use App\Models\Group;
use App\Models\Plan;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Student>
 */
class StudentFactory extends Factory
{
    protected $model = Student::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'first_name' => fake()->firstName(),
            'second_name' => fake()->firstName(),
            'middle_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'full_name' => static function (array $attributes): string {
                return trim(sprintf(
                    '%s %s %s %s',
                    (string) ($attributes['first_name'] ?? ''),
                    (string) ($attributes['second_name'] ?? ''),
                    (string) ($attributes['middle_name'] ?? ''),
                    (string) ($attributes['last_name'] ?? ''),
                ));
            },
            'id_number' => fake()->optional(0.85)->numerify('##########'),
            'parent_phone_number' => '9627'.fake()->unique()->numerify('#######'),
            'phone_number' => '9627'.fake()->unique()->numerify('#######'),
            'email' => fake()->unique()->safeEmail(),
            'date_of_birth' => fake()->dateTimeBetween('-18 years', '-8 years')->format('Y-m-d'),
            'center_id' => Center::factory(),
            'group_id' => Group::factory(),
            'plan_type_id' => Plan::factory(),
            'admin_id' => User::factory(),
            'is_active' => fake()->randomElement([
                Student::STATUS_ACTIVE,
                Student::STATUS_ACTIVE,
                Student::STATUS_ACTIVE,
                Student::STATUS_ACTIVE,
                Student::STATUS_INACTIVE,
                Student::STATUS_FROZEN,
            ]),
            'deducted_points_count' => fake()->numberBetween(0, 30),
        ];
    }

    public function active(): static
    {
        return $this->state(fn (): array => ['is_active' => Student::STATUS_ACTIVE]);
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['is_active' => Student::STATUS_INACTIVE]);
    }

    public function frozen(): static
    {
        return $this->state(fn (): array => ['is_active' => Student::STATUS_FROZEN]);
    }
}

