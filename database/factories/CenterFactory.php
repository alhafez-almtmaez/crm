<?php

namespace Database\Factories;

use App\Models\Center;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Center>
 */
class CenterFactory extends Factory
{
    protected $model = Center::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $allDays = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];
        $workingDays = fake()->randomElements($allDays, fake()->numberBetween(3, 6));
        sort($workingDays);

        return [
            'name' => fake()->unique()->bothify('Center-##??'),
            'phone' => '9627'.fake()->unique()->numerify('#######'),
            'group_serialized' => fake()->boolean(70)
                ? '1203630'.fake()->numerify('########').'@g.us'
                : null,
            'working_days' => $workingDays,
        ];
    }
}

