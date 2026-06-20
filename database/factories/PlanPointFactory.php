<?php

namespace Database\Factories;

use App\Models\Plan;
use App\Models\PlanPoint;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PlanPoint>
 */
class PlanPointFactory extends Factory
{
    protected $model = PlanPoint::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'plan_id' => Plan::factory(),
            'sort_order' => fake()->unique()->numberBetween(1, 500),
            'name' => fake()->words(3, true),
            'points' => fake()->numberBetween(1, 10),
            'weight' => 1,
            'is_standalone' => false,
            'requires_certificate' => false,
            'surah_name' => null,
            'part_name' => null,
            'three_parts' => null,
        ];
    }
}
