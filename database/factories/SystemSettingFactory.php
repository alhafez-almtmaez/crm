<?php

namespace Database\Factories;

use App\Models\SystemSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SystemSetting>
 */
class SystemSettingFactory extends Factory
{
    protected $model = SystemSetting::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'key' => fake()->unique()->bothify('setting_###_??'),
            'value' => [
                'seeded' => true,
                'created_at' => now()->toIso8601String(),
            ],
        ];
    }

    public function adminUi(): static
    {
        return $this->state(fn (): array => [
            'key' => 'admin_ui',
            'value' => [
                'brandName' => 'Orbit CRM',
                'brandTagline' => 'Seeded demo workspace',
                'language' => 'ar',
                'direction' => 'rtl',
                'timezone' => 'Asia/Amman',
                'dateFormat' => 'DD/MM/YYYY',
                'timeFormat' => 'HH:mm',
                'tokens' => [
                    'light' => [
                        'background' => '#ffffff',
                        'foreground' => '#0f172a',
                        'card' => '#fdfdfd',
                        'cardForeground' => '#0f172a',
                        'mutedForeground' => '#6b7280',
                        'border' => '#e8e8e8',
                        'accent' => '#059669',
                    ],
                    'dark' => [
                        'background' => '#010101',
                        'foreground' => '#e2e8f0',
                        'card' => '#0a0a0a',
                        'cardForeground' => '#e2e8f0',
                        'mutedForeground' => '#a3a3a3',
                        'border' => '#242424',
                        'accent' => '#059669',
                    ],
                ],
            ],
        ]);
    }
}

