<?php

namespace Database\Factories;

use App\Models\MessageTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MessageTemplate>
 */
class MessageTemplateFactory extends Factory
{
    protected $model = MessageTemplate::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'key' => fake()->unique()->bothify('msg.template.###.??'),
            'name' => fake()->words(3, true),
            'locale' => fake()->randomElement(['ar', 'en']),
            'content' => 'Dear {{full_name}}, absence type: {{attendance_label_ar}}, occurrence #{{occurrence_number}} on {{date}}.',
            'placeholders' => [
                'full_name',
                'attendance_label_ar',
                'occurrence_number',
                'date',
                'center_name',
            ],
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }
}

