<?php

namespace Database\Factories;

use App\Models\Device;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Device>
 */
class DeviceFactory extends Factory
{
    protected $model = Device::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true),
            'status' => fake()->randomElement(['PENDING', 'CONNECTED']),
            'session_id' => Str::slug(fake()->unique()->bothify('session-####-??'), '_'),
        ];
    }

    public function connected(): static
    {
        return $this->state(fn (): array => ['status' => 'CONNECTED']);
    }

    public function pending(): static
    {
        return $this->state(fn (): array => ['status' => 'PENDING']);
    }
}

