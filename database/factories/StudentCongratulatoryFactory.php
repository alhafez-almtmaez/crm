<?php

namespace Database\Factories;

use App\Models\Student;
use App\Models\StudentCongratulatory;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StudentCongratulatory>
 */
class StudentCongratulatoryFactory extends Factory
{
    protected $model = StudentCongratulatory::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'student_id' => Student::factory(),
            'reason' => fake()->sentence(8),
            'sent_by' => User::factory(),
        ];
    }
}

