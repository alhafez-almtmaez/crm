<?php

namespace Database\Factories;

use App\Models\Center;
use App\Models\Group;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Group>
 */
class GroupFactory extends Factory
{
    protected $model = Group::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->bothify('Group-##??'),
            'center_id' => Center::factory(),
        ];
    }
}

