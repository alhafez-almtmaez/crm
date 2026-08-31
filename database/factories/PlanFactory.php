<?php

namespace Database\Factories;

use App\Models\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Plan>
 */
class PlanFactory extends Factory
{
    protected $model = Plan::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->bothify('Plan-###-??'),
            // Quran is the backwards-compatible default for existing factories.
            'category' => Plan::CATEGORY_QURAN,
        ];
    }

    public function quran(): static
    {
        return $this->state(fn (): array => [
            'category' => Plan::CATEGORY_QURAN,
        ]);
    }

    public function sunnah(): static
    {
        return $this->state(fn (): array => [
            'category' => Plan::CATEGORY_SUNNAH,
        ]);
    }
}
