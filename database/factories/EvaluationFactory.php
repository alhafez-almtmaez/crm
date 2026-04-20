<?php

namespace Database\Factories;

use App\Models\Center;
use App\Models\Evaluation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Evaluation>
 */
class EvaluationFactory extends Factory
{
    protected $model = Evaluation::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ulid' => (string) Str::ulid(),
            'date' => fake()->dateTimeBetween('-30 days', 'now')->format('Y-m-d'),
            'center_id' => Center::factory(),
            'admin_id' => User::factory(),
            'is_send_absence_alerts' => fake()->boolean(35),
            'evaluation_type' => fake()->randomElement([Evaluation::TYPE_ALHIFZ, Evaluation::TYPE_TAJWID]),
        ];
    }
}
